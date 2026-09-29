<x-layouts.app title="Đổi mật khẩu" :subtitle="auth()->user()->name.' · '.auth()->user()->email">
    <form method="POST" action="{{ route('profile.update') }}" class="card grid max-w-md gap-4 p-5">
        @csrf @method('PUT')
        <div>
            <label class="label" for="current_password">Mật khẩu hiện tại</label>
            <input class="input" type="password" name="current_password" id="current_password" required autocomplete="current-password">
        </div>
        <div>
            <label class="label" for="password">Mật khẩu mới <span class="label-hint">(ít nhất 8 ký tự)</span></label>
            <input class="input" type="password" name="password" id="password" required minlength="8" autocomplete="new-password">
        </div>
        <div>
            <label class="label" for="password_confirmation">Nhập lại mật khẩu mới</label>
            <input class="input" type="password" name="password_confirmation" id="password_confirmation" required autocomplete="new-password">
        </div>
        <button class="btn btn-primary justify-self-start">Đổi mật khẩu</button>
    </form>
</x-layouts.app>
