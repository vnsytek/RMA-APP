<x-layouts.app title="Đổi mật khẩu" :subtitle="auth()->user()->name.', '.auth()->user()->email">
    <form method="POST" action="{{ route('profile.update') }}" class="card grid max-w-md gap-5 p-5 sm:p-6">
        @csrf @method('PUT')
        @foreach ([
            ['current_password', 'Mật khẩu hiện tại', null, 'current-password'],
            ['password', 'Mật khẩu mới', 'Ít nhất 8 ký tự. Nên trộn chữ và số.', 'new-password'],
            ['password_confirmation', 'Nhập lại mật khẩu mới', null, 'new-password'],
        ] as [$name, $label, $hint, $autocomplete])
            <div>
                <label class="label" for="{{ $name }}">{{ $label }}</label>
                <div class="relative">
                    <input class="input pr-11" type="password" name="{{ $name }}" id="{{ $name }}" required autocomplete="{{ $autocomplete }}"
                           @if ($name !== 'current_password') minlength="8" @endif @error($name) aria-invalid="true" @enderror>
                    <button type="button" class="absolute inset-y-0 right-0 grid w-11 cursor-pointer place-items-center rounded-r-lg text-muted hover:text-ink"
                            data-password-toggle="{{ $name }}" aria-pressed="false" aria-label="Hiện mật khẩu">
                        <x-icon name="eye" class="size-[18px]" data-state="hidden" />
                        <x-icon name="eye-off" class="size-[18px]" data-state="shown" hidden />
                    </button>
                </div>
                @if ($hint)
                    <p class="hint">{{ $hint }}</p>
                @endif
                <x-field-error :name="$name" />
            </div>
        @endforeach
        <div class="flex justify-end border-t border-line pt-4">
            <button class="btn btn-primary">Đổi mật khẩu</button>
        </div>
    </form>
</x-layouts.app>
