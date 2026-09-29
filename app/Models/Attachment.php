<?php

namespace App\Models;

use App\Enums\AttachmentStage;
use App\Enums\AttachmentType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['rma_ticket_id', 'rma_ticket_status_log_id', 'stage', 'doc_type', 'file_name', 'file_path', 'mime_type', 'size_bytes', 'uploaded_by'])]
class Attachment extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stage' => AttachmentStage::class,
            'doc_type' => AttachmentType::class,
            'size_bytes' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<RmaTicket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(RmaTicket::class, 'rma_ticket_id');
    }

    /**
     * The workflow step the photo was taken at, when it was uploaded with one.
     *
     * @return BelongsTo<RmaTicketStatusLog, $this>
     */
    public function statusLog(): BelongsTo
    {
        return $this->belongsTo(RmaTicketStatusLog::class, 'rma_ticket_status_log_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    public function humanSize(): string
    {
        return $this->size_bytes >= 1048576
            ? number_format($this->size_bytes / 1048576, 1, ',', '.').' MB'
            : max(1, (int) round($this->size_bytes / 1024)).' KB';
    }
}
