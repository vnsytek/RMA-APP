<?php

namespace App\Models;

use App\Enums\ClaimResult;
use App\Enums\OnsiteLocation;
use App\Enums\QuoteStatus;
use App\Enums\ServiceType;
use App\Enums\ShipmentOutcome;
use App\Enums\TicketKind;
use App\Enums\TicketResult;
use App\Enums\TicketStatus;
use App\Enums\WarrantyStatus;
use Carbon\CarbonInterface;
use Database\Factories\RmaTicketFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

#[Fillable([
    'ticket_no', 'service_type', 'original_service_type', 'onsite_location', 'warranty_status', 'customer_id', 'device_id',
    'returned_device_id', 'technician_id', 'fault_description', 'accessories', 'received_date', 'returned_date', 'status',
    'result', 'scrap_reason', 'repair_warranty_months', 'warranty_exclusions', 'claim_ticket_id', 'claim_item_id', 'claim_result',
    'claim_note', 'quote_status', 'quoted_at', 'quote_decided_at', 'is_chargeable', 'charge_amount', 'erp_receipt_no', 'erp_return_no',
    'note', 'created_by',
])]
class RmaTicket extends Model
{
    /** @use HasFactory<RmaTicketFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'service_type' => ServiceType::class,
            'original_service_type' => ServiceType::class,
            'onsite_location' => OnsiteLocation::class,
            'warranty_status' => WarrantyStatus::class,
            'status' => TicketStatus::class,
            'result' => TicketResult::class,
            'claim_result' => ClaimResult::class,
            'quote_status' => QuoteStatus::class,
            'received_date' => 'date',
            'returned_date' => 'date',
            'quoted_at' => 'datetime',
            'quote_decided_at' => 'datetime',
            'is_chargeable' => 'boolean',
            'charge_amount' => 'integer',
            'repair_warranty_months' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'ticket_no';
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<Device, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /**
     * The device handed back when the vendor swapped it (Seri trả).
     *
     * @return BelongsTo<Device, $this>
     */
    public function returnedDevice(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'returned_device_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<RmaCenterShipment, $this>
     */
    public function shipments(): HasMany
    {
        return $this->hasMany(RmaCenterShipment::class)->orderBy('id');
    }

    /**
     * @return HasOne<RmaCenterShipment, $this>
     */
    public function pendingShipment(): HasOne
    {
        return $this->hasOne(RmaCenterShipment::class)->where('outcome', ShipmentOutcome::Pending);
    }

    /**
     * @return HasOne<RmaCenterShipment, $this>
     */
    public function latestShipment(): HasOne
    {
        return $this->hasOne(RmaCenterShipment::class)->latestOfMany();
    }

    /**
     * @return HasMany<RmaQuoteItem, $this>
     */
    public function quoteItems(): HasMany
    {
        return $this->hasMany(RmaQuoteItem::class)->orderBy('id');
    }

    /**
     * @return HasMany<RmaReplacedPart, $this>
     */
    public function replacedParts(): HasMany
    {
        return $this->hasMany(RmaReplacedPart::class)->orderBy('id');
    }

    /**
     * @return HasMany<RmaTicketStatusLog, $this>
     */
    public function statusLogs(): HasMany
    {
        return $this->hasMany(RmaTicketStatusLog::class)->orderBy('id');
    }

    /**
     * @return HasMany<Attachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class)->orderBy('id');
    }

    public function kind(): TicketKind
    {
        return TicketKind::of($this->service_type, $this->onsite_location);
    }

    public function originalKind(): TicketKind
    {
        return TicketKind::of($this->original_service_type, $this->onsite_location);
    }

    public function isClosed(): bool
    {
        return $this->status->isClosed();
    }

    /**
     * Seri trả: the vendor's replacement when there is one, otherwise the received device.
     */
    public function currentDevice(): Device
    {
        return $this->returnedDevice ?? $this->device;
    }

    public function isSwappedToOtherProduct(): bool
    {
        return $this->returnedDevice !== null && $this->returnedDevice->product_model_id !== $this->device->product_model_id;
    }

    public function quoteTotal(): int
    {
        return (int) $this->quoteItems->sum(fn (RmaQuoteItem $item) => $item->lineTotal());
    }

    public function wasConverted(): bool
    {
        return $this->original_service_type !== $this->service_type;
    }

    /**
     * Receipt / return slips exist whenever Sang Y takes the device in.
     */
    public function printsSlips(): bool
    {
        return $this->originalKind()->takesDeviceIn();
    }

    /**
     * @return HasMany<RmaWarrantyItem, $this>
     */
    public function warrantyItems(): HasMany
    {
        return $this->hasMany(RmaWarrantyItem::class)->orderBy('id')->chaperone('ticket');
    }

    /**
     * The earlier repair whose warranty the customer is claiming.
     *
     * @return BelongsTo<RmaTicket, $this>
     */
    public function claimTicket(): BelongsTo
    {
        return $this->belongsTo(RmaTicket::class, 'claim_ticket_id');
    }

    /**
     * @return BelongsTo<RmaWarrantyItem, $this>
     */
    public function claimItem(): BelongsTo
    {
        return $this->belongsTo(RmaWarrantyItem::class, 'claim_item_id');
    }

    /**
     * Later tickets that claimed this repair's warranty.
     *
     * @return HasMany<RmaTicket, $this>
     */
    public function claims(): HasMany
    {
        return $this->hasMany(RmaTicket::class, 'claim_ticket_id');
    }

    /**
     * A warranty claim IT has not decided on yet.
     */
    public function claimPending(): bool
    {
        return $this->claim_ticket_id !== null && $this->claim_result === null;
    }

    /**
     * @return list<string>
     */
    public function warrantyExclusionList(): array
    {
        return text_lines($this->warranty_exclusions);
    }

    /**
     * Warranty items still valid today. A ticket from before itemised warranties has none.
     *
     * @return Collection<int, RmaWarrantyItem>
     */
    public function activeWarrantyItems(?CarbonInterface $today = null): Collection
    {
        return $this->warrantyItems->filter(fn (RmaWarrantyItem $item) => $item->isActive($today))->values();
    }

    /**
     * Whether any part of Sang Y's repair warranty on this ticket is still running.
     */
    public function repairWarrantyIsActive(?CarbonInterface $today = null): bool
    {
        return $this->repairWarrantyEndsOn()?->gte(($today ?? today())->copy()->startOfDay()) === true;
    }

    public function hasRepairWarranty(): bool
    {
        return $this->service_type === ServiceType::Repair
            && $this->result?->carriesRepairWarranty() === true
            && $this->repair_warranty_months > 0;
    }

    /**
     * End of Sang Y's repair warranty, counted from the return date (null until returned).
     */
    public function repairWarrantyEndsOn(?CarbonInterface $returnedOn = null): ?CarbonInterface
    {
        $from = $returnedOn ?? $this->returned_date;

        return $this->hasRepairWarranty() && $from !== null
            ? $from->copy()->addMonthsNoOverflow($this->repair_warranty_months)
            : null;
    }

    #[Scope]
    protected function open(Builder $query): Builder
    {
        return $query->whereIn('status', TicketStatus::open());
    }

    #[Scope]
    protected function ofKind(Builder $query, ?TicketKind $kind): Builder
    {
        if ($kind === null) {
            return $query;
        }

        return $query->where('service_type', $kind->serviceType())
            ->when($kind->isOnsite(), fn (Builder $query) => $query->where('onsite_location', $kind->onsiteLocation()));
    }

    #[Scope]
    protected function search(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function (Builder $query) use ($like) {
            $query->where('ticket_no', 'like', $like)
                ->orWhere('erp_receipt_no', 'like', $like)
                ->orWhere('erp_return_no', 'like', $like)
                ->orWhereHas('customer', fn (Builder $customer) => $customer
                    ->where('name', 'like', $like)
                    ->orWhere('contact_name', 'like', $like)
                    ->orWhere('phone', 'like', $like))
                ->orWhereHas('device', fn (Builder $device) => $device
                    ->where('serial_number', 'like', $like)
                    ->orWhereHas('productModel', fn (Builder $model) => $model->where('code', 'like', $like)))
                ->orWhereHas('returnedDevice', fn (Builder $device) => $device->where('serial_number', 'like', $like))
                ->orWhereHas('shipments', fn (Builder $shipment) => $shipment->where('vendor_case_no', 'like', $like));
        });
    }
}
