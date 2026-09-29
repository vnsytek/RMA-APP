@props(['ticket'])

@php
    use App\Enums\AttachmentStage;
    use App\Enums\AttachmentType;
    use App\Enums\TicketStatus;

    $groups = collect(AttachmentStage::cases())
        ->map(fn (AttachmentStage $stage) => ['stage' => $stage, 'items' => $ticket->attachments->filter(fn ($attachment) => $attachment->stage === $stage)])
        ->filter(fn (array $group) => $group['items']->isNotEmpty());

    $hasPhoto = fn (AttachmentStage $stage) => $ticket->attachments->contains(fn ($attachment) => $attachment->stage === $stage && $attachment->isImage());
    $missing = collect([
        AttachmentStage::Intake->value => $ticket->originalKind()->takesDeviceIn(),
        AttachmentStage::Return->value => $ticket->status === TicketStatus::Returned,
    ])->filter()->keys()->map(fn (string $stage) => AttachmentStage::from($stage))->reject($hasPhoto)->values();

    $defaultStage = old('stage', $missing->first()?->value ?? AttachmentStage::Other->value);
@endphp

<div id="chung-tu" class="space-y-4">
    @foreach ($missing as $stage)
        <p class="rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900" role="alert">
            Phiếu chưa có ảnh tình trạng máy <strong>{{ mb_strtolower($stage->label()) }}</strong>. Hãy chụp bổ sung ở ô bên dưới (chọn giai đoạn "{{ $stage->label() }}").
        </p>
    @endforeach

    @forelse ($groups as $group)
        <div>
            <h3 class="mb-2 flex flex-wrap items-baseline gap-x-2 text-xs font-semibold tracking-wider text-muted uppercase">
                {{ $group['stage']->label() }}
                <span class="font-normal tracking-normal normal-case">· {{ $group['items']->count() }} tệp</span>
            </h3>
            <div class="grid gap-2 sm:grid-cols-2">
                @foreach ($group['items'] as $attachment)
                    <div class="flex min-w-0 items-center gap-3 rounded-lg border border-line bg-panel p-2">
                        <a href="{{ route('tickets.attachments.show', [$ticket, $attachment]) }}" target="_blank" rel="noopener"
                           class="grid size-16 shrink-0 place-items-center overflow-hidden rounded-md border border-line bg-white font-mono text-[11px] font-semibold text-muted"
                           aria-label="Xem {{ $attachment->file_name }}">
                            @if ($attachment->isImage())
                                <img src="{{ route('tickets.attachments.show', [$ticket, $attachment]) }}" alt="" class="size-full object-cover" loading="lazy">
                            @else
                                PDF
                            @endif
                        </a>
                        <div class="min-w-0 flex-1 text-xs">
                            <div class="font-semibold">{{ $attachment->doc_type->label() }}</div>
                            <a href="{{ route('tickets.attachments.show', [$ticket, $attachment, 'download' => 1]) }}" class="block truncate font-mono text-brand hover:underline" title="Tải về {{ $attachment->file_name }}">{{ $attachment->file_name }}</a>
                            <div class="text-muted">{{ $attachment->humanSize() }} · {{ $attachment->uploader->name }} · {{ $attachment->created_at->format('d/m/Y H:i') }}</div>
                        </div>
                        @if (auth()->user()->isAdmin() || $attachment->uploaded_by === auth()->id())
                            <form method="POST" action="{{ route('tickets.attachments.destroy', [$ticket, $attachment]) }}" data-confirm="Xoá tệp {{ $attachment->file_name }}?">
                                @csrf
                                @method('DELETE')
                                <button class="cursor-pointer rounded px-1.5 text-lg leading-none text-muted hover:text-red-700" aria-label="Xoá {{ $attachment->file_name }}">×</button>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @empty
        <p class="text-sm text-muted">Chưa có ảnh hoặc chứng từ.</p>
    @endforelse
</div>

<x-field-error name="attachment" bag="attachment" class="mt-3" />

<form method="POST" action="{{ route('tickets.attachments.store', $ticket) }}" enctype="multipart/form-data" class="mt-4 grid gap-3 border-t border-line pt-4 sm:grid-cols-[auto_auto_1fr_auto] sm:items-end">
    @csrf
    <div>
        <label class="label" for="stage">Giai đoạn</label>
        <select name="stage" id="stage" class="input">
            @foreach (AttachmentStage::cases() as $stage)
                <option value="{{ $stage->value }}" @selected($defaultStage === $stage->value)>{{ $stage->label() }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="label" for="doc_type">Loại</label>
        <select name="doc_type" id="doc_type" class="input">
            @foreach (AttachmentType::cases() as $type)
                <option value="{{ $type->value }}" @selected(old('doc_type', AttachmentType::Photo->value) === $type->value)>{{ $type->label() }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="label" for="files">Tải bổ sung ảnh hoặc PDF <span class="label-hint">(nhiều tệp, mỗi tệp tối đa {{ intdiv(config('rma.attachments.max_kb'), 1024) }} MB)</span></label>
        <input type="file" name="files[]" id="files" multiple accept="image/*,application/pdf" required class="input py-1.5" data-file-preview="files-preview">
    </div>
    <button class="btn btn-secondary">Tải lên</button>
    <div id="files-preview" class="flex flex-wrap gap-2 sm:col-span-4"></div>
</form>
@foreach (['stage', 'files', 'files.*', 'doc_type'] as $field)
    <x-field-error :name="$field" bag="attachment" />
@endforeach
