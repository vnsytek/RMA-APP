<?php

namespace App\Http\Controllers;

use App\Enums\TicketKind;
use App\Enums\TicketStatus;
use App\Enums\WarrantyStatus;
use App\Http\Requests\StoreRmaTicketRequest;
use App\Models\Brand;
use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\DeviceType;
use App\Models\ProductModel;
use App\Models\RmaTicket;
use App\Models\ServiceCenter;
use App\Models\User;
use App\Services\CatalogResolver;
use App\Services\DeviceTracker;
use App\Services\TicketEditor;
use App\Services\TicketIntake;
use App\Services\TicketNumberGenerator;
use App\Services\TicketWorkflow;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RmaTicketController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $filters = $request->validate([
            'kind' => ['nullable', Rule::enum(TicketKind::class)],
            'status' => ['nullable', Rule::enum(TicketStatus::class)],
            'q' => ['nullable', 'string', 'max:100'],
            'technician' => ['nullable', 'integer'],
        ]);

        $user = $request->user();
        $kind = isset($filters['kind']) ? TicketKind::from($filters['kind']) : null;
        $status = isset($filters['status']) ? TicketStatus::from($filters['status']) : null;
        $technician = $user->isAdmin() ? ($filters['technician'] ?? null) : null;
        $visible = fn () => RmaTicket::query()
            ->visibleTo($user)
            ->when($technician, fn (Builder $query) => $query->where('technician_id', $technician));
        $term = trim($filters['q'] ?? '');

        // A scanned or typed ticket number / serial that matches exactly one ticket opens it straight away.
        if ($term !== '') {
            $exact = $visible()
                ->ofKind($kind)
                ->when($status, fn (Builder $query) => $query->where('status', $status))
                ->where(fn (Builder $query) => $query
                    ->where('ticket_no', $term)
                    ->orWhereHas('device', fn (Builder $device) => $device->where('serial_number', $term))
                    ->orWhereHas('returnedDevice', fn (Builder $device) => $device->where('serial_number', $term)))
                ->limit(2)
                ->pluck('ticket_no');

            if ($exact->count() === 1) {
                return redirect()->route('tickets.show', $exact->first());
            }
        }

        $tickets = $visible()
            ->ofKind($kind)
            ->when($status, fn (Builder $query) => $query->where('status', $status))
            ->search($filters['q'] ?? null)
            ->with(['customer', 'device.productModel.brand', 'device.productModel.deviceType', 'returnedDevice.productModel.brand', 'technician', 'quoteItems'])
            ->orderByDesc('ticket_no')
            ->paginate(25)
            ->withQueryString();

        return view('tickets.index', [
            'tickets' => $tickets,
            'kind' => $kind,
            'status' => $status,
            'search' => $filters['q'] ?? '',
            'technician' => $technician,
            'technicians' => $user->isAdmin() ? User::orderBy('name')->get(['id', 'name', 'is_active']) : collect(),
            'kindCounts' => collect(TicketKind::cases())->mapWithKeys(fn (TicketKind $type) => [$type->value => $visible()->ofKind($type)->count()]),
            'statusCounts' => $visible()->ofKind($kind)->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function create(Request $request, TicketNumberGenerator $numbers): View
    {
        return view('tickets.create', [
            'nextTicketNo' => $numbers->peek(today()),
            'deviceTypes' => DeviceType::active()->orderBy('name')->get(),
            'brands' => Brand::active()->orderBy('name')->get(),
            'models' => ProductModel::active()->with('brand')->orderBy('code')->get(),
            'customers' => Customer::orderBy('name')->get(['id', 'code', 'short_name', 'name', 'phone', 'tax_code']),
            'contacts' => CustomerContact::active()->orderBy('name')->get(['id', 'customer_id', 'name', 'phone'])
                ->groupBy('customer_id')->map(fn ($group) => $group->map->only(['id', 'name', 'phone'])->values()),
            'technicians' => User::active()->orderBy('name')->get(),
            'serviceCenters' => ServiceCenter::active()->orderBy('name')->get(),
            'prefill' => $request->only(['serial', 'customer']),
        ]);
    }

    public function store(StoreRmaTicketRequest $request, TicketIntake $intake): RedirectResponse
    {
        $ticket = $intake->open($request->safe()->except('photos'), $request->file('photos', []), $request->user());

        return redirect()->route('tickets.show', $ticket)->with('status', "Đã lập phiếu {$ticket->ticket_no}.");
    }

    public function show(RmaTicket $ticket, TicketWorkflow $workflow, DeviceTracker $tracker): View
    {
        $ticket->load([
            'customer', 'device.productModel.brand', 'device.productModel.deviceType', 'returnedDevice.productModel.brand',
            'returnedDevice.productModel.deviceType', 'technician', 'creator', 'shipments.serviceCenter', 'quoteItems',
            'replacedParts.shipment', 'statusLogs.user', 'statusLogs.attachments', 'edits.user', 'attachments.uploader',
            'warrantyItems', 'claims', 'claimTicket.warrantyItems',
        ]);

        $summary = $tracker->for($ticket->currentDevice());

        $timeline = $ticket->statusLogs->map(fn ($log) => ['type' => 'status', 'at' => $log->created_at, 'data' => $log])
            ->concat($ticket->edits->map(fn ($edit) => ['type' => 'edit', 'at' => $edit->created_at, 'data' => $edit]))
            ->sortBy('at')
            ->values();

        return view('tickets.show', [
            'ticket' => $ticket,
            'actions' => $workflow->availableActions($ticket),
            'device' => $summary,
            'history' => $summary->tickets->reject(fn (RmaTicket $other) => $other->is($ticket))->values(),
            'timeline' => $timeline,
            'serviceCenters' => ServiceCenter::active()->orderBy('name')->get(),
            'technicians' => User::active()->orderBy('name')->get(),
            'deviceTypes' => DeviceType::active()->orderBy('name')->get(),
            'brands' => Brand::active()->orderBy('name')->get(),
            'models' => ProductModel::active()->with('brand', 'deviceType')->get()->sortBy(fn (ProductModel $model) => $model->deviceType->name.' '.$model->shortName()),
        ]);
    }

    /**
     * Notes for everyone who works on the ticket; only an admin changes who is in charge.
     */
    public function update(Request $request, RmaTicket $ticket): RedirectResponse
    {
        abort_if($ticket->isClosed() && ! $request->user()->isAdmin(), 403, 'Phiếu đã đóng, không sửa được.');

        $canAssign = $request->user()->can('assign', $ticket);

        $data = $request->validate([
            ...($canAssign ? ['technician_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('is_active', true)]] : []),
            'note' => ['nullable', 'string', 'max:2000'],
        ], attributes: ['technician_id' => 'nhân viên phụ trách', 'note' => 'ghi chú']);

        $ticket->update($data);

        return back()->with('status', 'Đã lưu thông tin phiếu.');
    }

    /**
     * Admins remove a ticket opened by mistake. It is soft-deleted, so its number is never reused.
     */
    public function destroy(RmaTicket $ticket): RedirectResponse
    {
        $ticket->delete();

        return redirect()->route('tickets.index')->with('status', "Đã xoá phiếu {$ticket->ticket_no}.");
    }

    /**
     * Correct a data-entry mistake (wrong serial, typo'd fault description, wrong date) on an
     * existing ticket, open or closed. Unlike update(), every change here is logged, not silently
     * overwritten — see TicketEditor.
     */
    public function correct(Request $request, RmaTicket $ticket, TicketEditor $editor, CatalogResolver $catalog): RedirectResponse
    {
        $data = $request->validateWithBag('correct', [
            'product_model_id' => [
                'nullable',
                function (string $attribute, mixed $value, Closure $fail) use ($request): void {
                    $type = $request->input('device_type_id');
                    $brand = $request->input('brand_id');

                    if ($value === 'new') {
                        return;
                    }

                    $model = ProductModel::active()->find($value);

                    if ($model === null) {
                        $fail('Model không hợp lệ.');
                    } elseif ((filled($type) && (int) $type !== $model->device_type_id) || (filled($brand) && (int) $brand !== $model->brand_id)) {
                        $fail('Model này không thuộc loại / hãng đã chọn.');
                    }
                },
            ],
            'device_type_id' => ['required_if:product_model_id,new', 'nullable', 'integer', Rule::exists('device_types', 'id')],
            'brand_id' => ['required_if:product_model_id,new', 'nullable', 'integer', Rule::exists('brands', 'id')],
            'new_model_code' => ['required_if:product_model_id,new', 'nullable', 'string', 'max:100'],
            'serial_number' => ['required', 'string', 'max:100'],
            'warranty_status' => [
                'required', Rule::enum(WarrantyStatus::class),
                function (string $attribute, mixed $value, Closure $fail) use ($ticket): void {
                    if ($value === WarrantyStatus::OutOfWarranty->value && ! $ticket->kind()->allowsOutOfWarranty()) {
                        $fail('Phiếu bảo hành hãng không thể là hết bảo hành.');
                    }
                },
            ],
            'fault_description' => ['required', 'string', 'max:2000'],
            'received_date' => [
                'required', 'date', 'before_or_equal:today',
                function (string $attribute, mixed $value, Closure $fail) use ($ticket): void {
                    if ($ticket->returned_date && Carbon::parse($value)->gt($ticket->returned_date)) {
                        $fail('Ngày không được sau ngày trả ('.vn_date($ticket->returned_date).').');
                    }
                },
            ],
            'note' => ['nullable', 'string', 'max:500'],
        ], attributes: ['product_model_id' => 'model', 'device_type_id' => 'loại thiết bị', 'brand_id' => 'hãng', 'new_model_code' => 'mã model mới', 'serial_number' => 'serial', 'warranty_status' => 'tình trạng bảo hành', 'fault_description' => 'lỗi khách báo', 'received_date' => 'ngày nhận', 'note' => 'lý do sửa']);

        $data['product_model_id'] = match (true) {
            blank($data['product_model_id'] ?? null) => $ticket->device->product_model_id,
            $data['product_model_id'] === 'new' => $catalog->productModel('new', $data['new_model_code'], $catalog->deviceType($data['device_type_id'], null), $catalog->brand($data['brand_id'], null))->id,
            default => (int) $data['product_model_id'],
        };

        $editor->correct($ticket, $data, $request->user(), $data['note'] ?? null);

        return redirect()->route('tickets.show', $ticket)->with('status', "Đã cập nhật thông tin phiếu {$ticket->ticket_no}.");
    }
}
