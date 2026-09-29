<?php

namespace App\Http\Controllers;

use App\Enums\TicketKind;
use App\Enums\TicketStatus;
use App\Http\Requests\StoreRmaTicketRequest;
use App\Models\Brand;
use App\Models\Customer;
use App\Models\DeviceType;
use App\Models\ProductModel;
use App\Models\RmaTicket;
use App\Models\ServiceCenter;
use App\Models\User;
use App\Services\DeviceTracker;
use App\Services\TicketIntake;
use App\Services\TicketNumberGenerator;
use App\Services\TicketWorkflow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RmaTicketController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'kind' => ['nullable', Rule::enum(TicketKind::class)],
            'status' => ['nullable', Rule::enum(TicketStatus::class)],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $kind = isset($filters['kind']) ? TicketKind::from($filters['kind']) : null;
        $status = isset($filters['status']) ? TicketStatus::from($filters['status']) : null;

        $tickets = RmaTicket::query()
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
            'kindCounts' => collect(TicketKind::cases())->mapWithKeys(fn (TicketKind $type) => [$type->value => RmaTicket::query()->ofKind($type)->count()]),
            'statusCounts' => RmaTicket::query()->ofKind($kind)->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function create(Request $request, TicketNumberGenerator $numbers): View
    {
        return view('tickets.create', [
            'nextTicketNo' => $numbers->peek(today()),
            'deviceTypes' => DeviceType::active()->orderBy('name')->get(),
            'brands' => Brand::active()->orderBy('name')->get(),
            'models' => ProductModel::active()->with('brand')->orderBy('code')->get(),
            'customers' => Customer::orderBy('name')->get(['id', 'name', 'phone']),
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
            'replacedParts.shipment', 'statusLogs.user', 'statusLogs.attachments', 'attachments.uploader', 'warrantyItems', 'claims', 'claimTicket.warrantyItems',
        ]);

        $summary = $tracker->for($ticket->currentDevice());

        return view('tickets.show', [
            'ticket' => $ticket,
            'actions' => $workflow->availableActions($ticket),
            'device' => $summary,
            'history' => $summary->tickets->reject(fn (RmaTicket $other) => $other->is($ticket))->values(),
            'serviceCenters' => ServiceCenter::active()->orderBy('name')->get(),
            'technicians' => User::active()->orderBy('name')->get(),
            'models' => ProductModel::active()->with('brand', 'deviceType')->get()->sortBy(fn (ProductModel $model) => $model->deviceType->name.' '.$model->shortName()),
        ]);
    }

    public function update(Request $request, RmaTicket $ticket): RedirectResponse
    {
        abort_if($ticket->isClosed(), 403, 'Phiếu đã đóng, không sửa được.');

        $data = $request->validate([
            'technician_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('is_active', true)],
            'note' => ['nullable', 'string', 'max:2000'],
        ], attributes: ['technician_id' => 'nhân viên phụ trách', 'note' => 'ghi chú']);

        $ticket->update($data);

        return back()->with('status', 'Đã lưu thông tin phiếu.');
    }
}
