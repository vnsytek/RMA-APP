<?php

namespace App\Services;

use App\Enums\AttachmentStage;
use App\Enums\AttachmentType;
use App\Models\Attachment;
use App\Models\RmaTicket;
use App\Models\RmaTicketStatusLog;
use App\Models\User;
use App\Rules\HasPhoto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AttachmentStorage
{
    public function store(
        RmaTicket $ticket,
        UploadedFile $file,
        AttachmentType $type,
        AttachmentStage $stage,
        User $user,
        ?RmaTicketStatusLog $log = null,
    ): Attachment {
        $path = $file->store('attachments/'.$ticket->ticket_no, config('rma.attachments.disk'));

        return $ticket->attachments()->create([
            'rma_ticket_status_log_id' => $log?->id,
            'stage' => $stage,
            'doc_type' => $type,
            'file_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'file_path' => $path,
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'size_bytes' => $file->getSize(),
            'uploaded_by' => $user->id,
        ]);
    }

    /**
     * Store the files uploaded at a stage: images are filed as device photos, PDFs by the stage.
     *
     * @param  iterable<UploadedFile>  $files
     */
    public function storeForStage(iterable $files, RmaTicket $ticket, AttachmentStage $stage, User $user, ?RmaTicketStatusLog $log = null): void
    {
        foreach ($files as $file) {
            $type = self::isPhoto($file) ? AttachmentType::Photo : $stage->documentType();
            $this->store($ticket, $file, $type, $stage, $user, $log);
        }
    }

    /**
     * Delete an attachment, refusing to remove the last photo of a stage that requires one.
     */
    public function delete(Attachment $attachment): void
    {
        if ($attachment->stage->requiresPhoto() && $attachment->isImage()) {
            $remaining = Attachment::query()
                ->where('rma_ticket_id', $attachment->rma_ticket_id)
                ->where('stage', $attachment->stage)
                ->where('mime_type', 'like', 'image/%')
                ->whereKeyNot($attachment->id)
                ->exists();

            if (! $remaining) {
                throw ValidationException::withMessages([
                    'attachment' => 'Phải còn ít nhất 1 ảnh "'.mb_strtolower($attachment->stage->label()).'". Hãy tải ảnh thay thế lên trước rồi mới xoá ảnh này.',
                ])->errorBag('attachment');
            }
        }

        Storage::disk(config('rma.attachments.disk'))->delete($attachment->file_path);
        $attachment->delete();
    }

    /**
     * Rules for one photo or document.
     *
     * @return list<string>
     */
    public static function fileRules(): array
    {
        return ['file', 'mimes:'.implode(',', config('rma.attachments.mimes')), 'max:'.config('rma.attachments.max_kb')];
    }

    /**
     * Rules for a multi-file input; a stage that requires a photo needs at least one image among the files.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function uploadRules(string $field, AttachmentStage $stage, bool $required): array
    {
        return [
            $field => [$required ? new HasPhoto($stage) : 'nullable', 'array', 'max:'.config('rma.attachments.max_files')],
            $field.'.*' => self::fileRules(),
        ];
    }

    /**
     * @param  iterable<mixed>  $files
     */
    public static function containsPhoto(iterable $files): bool
    {
        foreach ($files as $file) {
            if (self::isPhoto($file)) {
                return true;
            }
        }

        return false;
    }

    public static function missingPhotoMessage(AttachmentStage $stage): string
    {
        return 'Cần chụp ít nhất 1 ảnh tình trạng máy '.mb_strtolower($stage->label()).'.';
    }

    private static function isPhoto(mixed $file): bool
    {
        return $file instanceof UploadedFile && str_starts_with((string) $file->getMimeType(), 'image/');
    }
}
