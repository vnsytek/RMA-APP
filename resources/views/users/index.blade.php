<x-layouts.app title="Người dùng" subtitle="Tài khoản đăng nhập và quyền. Admin: toàn quyền. User: lập và xử lý phiếu.">
    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>Họ tên</th><th>Email</th><th>Quyền</th><th class="text-right">Phiếu phụ trách</th><th class="text-right">Đang giữ</th><th>Trạng thái</th><th>Đổi quyền / mật khẩu</th><th></th></tr></thead>
            <tbody>
                @foreach ($users as $user)
                    <tr @class(['opacity-60' => ! $user->is_active])>
                        <td class="font-medium">{{ $user->name }}</td>
                        <td class="font-mono text-xs">{{ $user->email }}</td>
                        <td><x-pill :tone="$user->isAdmin() ? 'violet' : 'gray'">{{ $user->role->label() }}</x-pill></td>
                        <td class="text-right">{{ $user->assigned_tickets_count }}</td>
                        <td class="text-right">{{ $user->open_tickets_count ?: '—' }}</td>
                        <td>{{ $user->is_active ? 'Đang dùng' : 'Đã khoá' }}</td>
                        <td>
                            <form method="POST" action="{{ route('users.update', $user) }}" class="flex flex-wrap gap-2">
                                @csrf @method('PUT')
                                <select class="input w-auto py-1" name="role" aria-label="Quyền của {{ $user->name }}">
                                    @foreach ($roles as $role)
                                        <option value="{{ $role->value }}" @selected($user->role === $role)>{{ $role->label() }}</option>
                                    @endforeach
                                </select>
                                <input class="input w-44 py-1" type="password" name="password" placeholder="Mật khẩu mới (tuỳ chọn)" autocomplete="new-password" aria-label="Mật khẩu mới của {{ $user->name }}">
                                <button class="btn btn-secondary btn-sm">Lưu</button>
                            </form>
                            <x-field-error name="role" :bag="'user'.$user->id" />
                            <x-field-error name="password" :bag="'user'.$user->id" />
                        </td>
                        <td class="text-right">
                            @if ($user->is(auth()->user()))
                                <span class="text-xs text-muted">đang đăng nhập</span>
                            @else
                                <form method="POST" action="{{ route('users.toggle', $user) }}">
                                    @csrf @method('PATCH')
                                    <button class="btn btn-secondary btn-sm">{{ $user->is_active ? 'Khoá' : 'Mở khoá' }}</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <section class="card mt-5 p-5">
        <h2 class="mb-3 font-semibold">Thêm tài khoản</h2>
        <form method="POST" action="{{ route('users.store') }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_140px_1fr_auto] lg:items-end">
            @csrf
            <div><label class="label" for="u-name">Họ tên *</label><input class="input" name="name" id="u-name" required maxlength="255" value="{{ old('name') }}"></div>
            <div><label class="label" for="u-email">Email *</label><input class="input" type="email" name="email" id="u-email" required maxlength="255" value="{{ old('email') }}"></div>
            <div>
                <label class="label" for="u-role">Quyền *</label>
                <select class="input" name="role" id="u-role">
                    @foreach ($roles as $role)
                        <option value="{{ $role->value }}" @selected(old('role', 'user') === $role->value)>{{ $role->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div><label class="label" for="u-password">Mật khẩu *</label><input class="input" type="password" name="password" id="u-password" required minlength="8" autocomplete="new-password"></div>
            <button class="btn btn-primary">Thêm</button>
        </form>
        @foreach (['name', 'email', 'role', 'password'] as $field)
            <x-field-error :name="$field" bag="user" />
        @endforeach
        <ul class="mt-3 list-disc space-y-0.5 pl-5 text-xs text-muted">
            @foreach ($roles as $role)
                <li><b>{{ $role->label() }}</b>: {{ $role->description() }}</li>
            @endforeach
        </ul>
    </section>
</x-layouts.app>
