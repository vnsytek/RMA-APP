@php
    $activeUsers = $users->where('is_active', true);
@endphp

<x-layouts.app title="Người dùng" subtitle="Tài khoản đăng nhập và quyền. Nhân viên nghỉ: bấm Sửa → Chuyển phiếu để giao phiếu đang mở cho người khác.">
    <x-slot:actions>
        <button type="button" class="btn btn-primary" data-dialog-open="user-new">+ Thêm tài khoản</button>
    </x-slot:actions>

    <div class="card overflow-x-auto">
        <table class="table">
            <thead>
                <tr><th>Họ tên</th><th>Email</th><th>Quyền</th><th class="text-right">Phiếu phụ trách</th><th class="text-right">Đang giữ</th><th>Trạng thái</th><th></th></tr>
            </thead>
            <tbody>
                @foreach ($users as $user)
                    <tr @class(['opacity-60' => ! $user->is_active])>
                        <td class="font-medium">
                            {{ $user->name }}
                            @if ($user->is(auth()->user()))<span class="text-xs font-normal text-muted">· bạn</span>@endif
                        </td>
                        <td class="font-mono text-xs">{{ $user->email }}</td>
                        <td><x-pill :tone="$user->isAdmin() ? 'violet' : 'gray'">{{ $user->role->label() }}</x-pill></td>
                        <td class="text-right tabular-nums">{{ $user->assigned_tickets_count }}</td>
                        <td class="text-right tabular-nums">
                            @if ($user->open_tickets_count)
                                <a href="{{ route('tickets.index', ['technician' => $user->id]) }}" class="font-semibold text-brand hover:underline">{{ $user->open_tickets_count }}</a>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td><x-pill :tone="$user->is_active ? 'green' : 'red'">{{ $user->is_active ? 'Đang dùng' : 'Đã khoá' }}</x-pill></td>
                        <td class="text-right">
                            <button type="button" class="btn btn-secondary btn-sm" data-dialog-open="user-{{ $user->id }}">Sửa</button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <ul class="mt-3 list-disc space-y-0.5 pl-5 text-xs text-muted">
        @foreach ($roles as $role)
            <li><b>{{ $role->label() }}</b>: {{ $role->description() }}</li>
        @endforeach
    </ul>

    {{-- New account --}}
    @php
        $newErrors = $errors->getBag('user');
    @endphp
    <dialog id="user-new" class="modal" aria-labelledby="user-new-title" @if ($newErrors->any()) data-open @endif>
        <form method="POST" action="{{ route('users.store') }}" class="modal-body">
            @csrf
            <div class="modal-head">
                <h2 id="user-new-title">Thêm tài khoản</h2>
                <button type="button" class="modal-x" data-dialog-close aria-label="Đóng">×</button>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <div class="sm:col-span-2"><label class="label" for="new-name">Họ tên *</label><input class="input" name="name" id="new-name" required maxlength="255" value="{{ $newErrors->any() ? old('name') : '' }}"></div>
                <div class="sm:col-span-2"><label class="label" for="new-email">Email đăng nhập *</label><input class="input" type="email" name="email" id="new-email" required maxlength="255" value="{{ $newErrors->any() ? old('email') : '' }}"></div>
                <div>
                    <label class="label" for="new-role">Quyền *</label>
                    <select class="input" name="role" id="new-role">
                        @foreach ($roles as $role)
                            <option value="{{ $role->value }}" @selected(($newErrors->any() ? old('role') : 'user') === $role->value)>{{ $role->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div><label class="label" for="new-password">Mật khẩu *</label><input class="input" type="password" name="password" id="new-password" required minlength="8" autocomplete="new-password"></div>
            </div>
            @foreach (['name', 'email', 'role', 'password'] as $field)
                <x-field-error :name="$field" bag="user" />
            @endforeach
            <div class="modal-foot">
                <button type="button" class="btn btn-secondary" data-dialog-close>Huỷ</button>
                <button class="btn btn-primary">Thêm tài khoản</button>
            </div>
        </form>
    </dialog>

    {{-- One edit dialog per account --}}
    @foreach ($users as $user)
        @php
            $bag = 'user'.$user->id;
            $failed = $errors->getBag($bag)->any();
            $handoverFailed = $errors->getBag('handover'.$user->id)->any();
            $isSelf = $user->is(auth()->user());
        @endphp
        <dialog id="user-{{ $user->id }}" class="modal" aria-labelledby="user-{{ $user->id }}-title" @if ($failed || $handoverFailed) data-open @endif>
            <div class="modal-body">
                <div class="modal-head">
                    <div>
                        <h2 id="user-{{ $user->id }}-title">Sửa tài khoản</h2>
                        <p class="text-xs text-muted">{{ $user->assigned_tickets_count }} phiếu phụ trách · {{ $user->open_tickets_count }} đang giữ</p>
                    </div>
                    <button type="button" class="modal-x" data-dialog-close aria-label="Đóng">×</button>
                </div>

                <form method="POST" action="{{ route('users.update', $user) }}" class="grid gap-3 sm:grid-cols-2">
                    @csrf @method('PUT')
                    <div class="sm:col-span-2"><label class="label" for="u{{ $user->id }}-name">Họ tên *</label><input class="input" name="name" id="u{{ $user->id }}-name" required maxlength="255" value="{{ $failed ? old('name') : $user->name }}"></div>
                    <div class="sm:col-span-2"><label class="label" for="u{{ $user->id }}-email">Email đăng nhập *</label><input class="input" type="email" name="email" id="u{{ $user->id }}-email" required maxlength="255" value="{{ $failed ? old('email') : $user->email }}"></div>
                    <div>
                        <label class="label" for="u{{ $user->id }}-role">Quyền *</label>
                        <select class="input" name="role" id="u{{ $user->id }}-role" @disabled($isSelf)>
                            @foreach ($roles as $role)
                                <option value="{{ $role->value }}" @selected(($failed ? old('role') : $user->role->value) === $role->value)>{{ $role->label() }}</option>
                            @endforeach
                        </select>
                        @if ($isSelf)
                            <input type="hidden" name="role" value="{{ $user->role->value }}">
                            <p class="mt-1 text-xs text-muted">Không tự đổi quyền của chính mình.</p>
                        @endif
                    </div>
                    <div>
                        <label class="label" for="u{{ $user->id }}-password">Mật khẩu mới <span class="label-hint">(để trống nếu giữ nguyên)</span></label>
                        <input class="input" type="password" name="password" id="u{{ $user->id }}-password" minlength="8" autocomplete="new-password">
                    </div>
                    <div class="sm:col-span-2">
                        @foreach (['name', 'email', 'role', 'password'] as $field)
                            <x-field-error :name="$field" :bag="$bag" />
                        @endforeach
                    </div>
                    <div class="modal-foot sm:col-span-2">
                        <button type="button" class="btn btn-secondary" data-dialog-close>Huỷ</button>
                        <button class="btn btn-primary">Lưu thay đổi</button>
                    </div>
                </form>

                @if ($user->open_tickets_count)
                    <form method="POST" action="{{ route('users.handover', $user) }}" class="modal-section" data-confirm="Chuyển {{ $user->open_tickets_count }} phiếu đang mở của {{ $user->name }} sang người đã chọn?">
                        @csrf
                        <h3>Chuyển phiếu đang giữ</h3>
                        <p class="mb-2 text-xs text-muted">Giao {{ $user->open_tickets_count }} phiếu đang mở của {{ $user->name }} cho người khác (khi nghỉ phép, nghỉ việc). Phiếu đã đóng giữ nguyên.</p>
                        <div class="flex flex-wrap gap-2">
                            <select class="input w-auto flex-1" name="to_user_id" required aria-label="Chuyển phiếu của {{ $user->name }} cho">
                                <option value="">Chọn người nhận…</option>
                                @foreach ($activeUsers->reject(fn ($other) => $other->is($user)) as $other)
                                    <option value="{{ $other->id }}">{{ $other->name }}</option>
                                @endforeach
                            </select>
                            <button class="btn btn-secondary">Chuyển {{ $user->open_tickets_count }} phiếu</button>
                        </div>
                        <x-field-error name="to_user_id" :bag="'handover'.$user->id" />
                    </form>
                @endif

                @unless ($isSelf)
                    <form method="POST" action="{{ route('users.toggle', $user) }}" class="modal-section flex flex-wrap items-center justify-between gap-2"
                          @if ($user->is_active) data-confirm="Khoá tài khoản {{ $user->name }}? Người này sẽ không đăng nhập được." @endif>
                        @csrf @method('PATCH')
                        <div>
                            <h3>{{ $user->is_active ? 'Khoá tài khoản' : 'Mở khoá tài khoản' }}</h3>
                            <p class="text-xs text-muted">{{ $user->is_active ? 'Không xoá dữ liệu, phiếu cũ vẫn giữ tên người này.' : 'Tài khoản đang bị khoá, không đăng nhập được.' }}</p>
                        </div>
                        <button @class(['btn', 'btn-danger' => $user->is_active, 'btn-secondary' => ! $user->is_active])>{{ $user->is_active ? 'Khoá' : 'Mở khoá' }}</button>
                    </form>
                @endunless
            </div>
        </dialog>
    @endforeach
</x-layouts.app>
