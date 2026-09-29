<?php

namespace App\Http\Controllers;

use App\Enums\AttachmentStage;
use App\Enums\AttachmentType;
use App\Models\Attachment;
use App\Models\RmaTicket;
use App\Services\AttachmentStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketAttachmentController extends Controller
{
    public function store(Request $request, RmaTicket $ticket, AttachmentStorage $storage): RedirectResponse
    {
        $data = $request->validateWithBag('attachment', [
            'stage' => ['required', Rule::enum(AttachmentStage::class)],
            'doc_type' => ['required', Rule::enum(AttachmentType::class)],
            'files' => ['required', 'array', 'max:'.config('rma.attachments.max_files')],
            'files.*' => ['required', ...AttachmentStorage::fileRules()],
        ], attributes: ['stage' => 'giai đoạn', 'doc_type' => 'loại chứng từ', 'files' => 'tệp', 'files.*' => 'tệp']);

        foreach ($request->file('files') as $file) {
            $storage->store($ticket, $file, AttachmentType::from($data['doc_type']), AttachmentStage::from($data['stage']), $request->user());
        }

        return redirect()->to(route('tickets.show', $ticket).'#chung-tu')->with('status', 'Đã tải lên '.count($data['files']).' tệp.');
    }

    public function show(Request $request, RmaTicket $ticket, Attachment $attachment): StreamedResponse
    {
        $disk = Storage::disk(config('rma.attachments.disk'));
        abort_unless($disk->exists($attachment->file_path), 404);

        return $disk->response($attachment->file_path, $attachment->file_name, [
            'Content-Type' => $attachment->mime_type,
            'X-Content-Type-Options' => 'nosniff',
        ], $request->boolean('download') ? 'attachment' : 'inline');
    }

    public function destroy(Request $request, RmaTicket $ticket, Attachment $attachment, AttachmentStorage $storage): RedirectResponse
    {
        abort_unless($request->user()->isAdmin() || $attachment->uploaded_by === $request->user()->id, 403, 'Chỉ người tải lên hoặc Admin mới xoá được chứng từ này.');

        $storage->delete($attachment);

        return redirect()->to(route('tickets.show', $ticket).'#chung-tu')->with('status', 'Đã xoá chứng từ.');
    }
}
