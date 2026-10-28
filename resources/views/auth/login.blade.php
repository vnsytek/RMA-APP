<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Đăng nhập · {{ config('app.name') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-white">
    @php
        $failed = $errors->any();
    @endphp

    <div class="grid min-h-screen lg:grid-cols-[minmax(0,1.1fr)_minmax(0,1fr)]">
        {{-- Brand side: the paper tag every device gets at the counter, hanging from its string. --}}
        <section class="relative hidden overflow-hidden bg-brand-dark text-side-text lg:flex lg:flex-col" aria-hidden="true">
            <div class="flex items-center gap-3 px-12 pt-10">
                <img src="{{ asset('images/logo-sangy-128.png') }}" alt="" class="size-11">
                <span class="leading-tight">
                    <span class="block text-sm font-bold tracking-[0.18em] text-white">RMA</span>
                    <span class="block text-xs text-side-muted">{{ config('rma.company.name') }}</span>
                </span>
            </div>

            <div class="relative flex flex-1 items-center justify-center px-12">
                <svg class="absolute top-0 left-1/2 h-[calc(50%-7.6rem)] w-24 -translate-x-1/2 overflow-visible text-[#c9b57f]" viewBox="0 0 96 100" preserveAspectRatio="none" fill="none">
                    <path d="M44 0 C 48 40, 50 70, 38 100" stroke="currentColor" stroke-width="1.5" vector-effect="non-scaling-stroke" />
                </svg>
                <div class="-rotate-[4deg] drop-shadow-[0_22px_28px_rgb(0_0_0/0.4)]">
                <div class="relative w-[19rem] bg-[#efe3c2] px-8 pt-16 pb-9 text-ink [clip-path:polygon(22%_0,78%_0,100%_11%,100%_100%,0_100%,0_11%)]">
                    <span class="absolute top-6 left-1/2 size-6 -translate-x-1/2 rounded-full border-[5px] border-[#d8c48e] bg-brand-dark"></span>
                    <p class="text-xs font-semibold tracking-[0.16em] text-[#7a6a3d]">{{ config('rma.company.short_name') }}</p>
                    <p class="mt-1 text-[4.5rem] leading-none font-bold tracking-tight text-brand-dark">RMA</p>
                    <p class="mt-2 text-sm font-medium text-[#5c5032]">Phiếu bảo hành và sửa chữa</p>
                    <dl class="mt-7 space-y-4 text-xs text-[#7a6a3d]">
                        @foreach (['Số phiếu', 'Serial', 'Khách hàng', 'Ngày nhận'] as $field)
                            <div class="flex items-end gap-2"><dt class="shrink-0">{{ $field }}</dt><dd class="h-px flex-1 border-b border-dashed border-[#bfae7c]"></dd></div>
                        @endforeach
                    </dl>
                </div>
                </div>
            </div>

            <p class="px-12 pb-10 text-2xl leading-snug font-semibold text-balance text-white">Theo dõi từng máy từ lúc nhận đến lúc trả khách.</p>
        </section>

        <section class="flex flex-col">
            <div class="flex items-center gap-3 bg-brand-dark px-5 py-4 lg:hidden">
                <img src="{{ asset('images/logo-sangy-128.png') }}" alt="" class="size-9">
                <span class="leading-tight">
                    <span class="block text-sm font-bold tracking-[0.18em] text-white">RMA</span>
                    <span class="block text-xs text-side-muted">{{ config('rma.company.short_name') }} · Bảo hành và sửa chữa</span>
                </span>
            </div>

            <div class="flex flex-1 items-center px-5 py-12 sm:px-12">
                <form method="POST" action="{{ url('/login') }}" class="mx-auto w-full max-w-sm">
                    @csrf
                    <h1 class="text-[1.75rem] leading-tight font-semibold tracking-tight">Đăng nhập</h1>
                    <p class="mt-2 text-muted">Dùng email công ty và mật khẩu Admin đã cấp cho bạn.</p>

                    @if ($failed)
                        <div class="alert alert-error mt-6" role="alert" id="login-error">
                            <x-icon name="alert" />
                            <p>{{ $errors->first() }}</p>
                        </div>
                    @endif

                    <div class="mt-7">
                        <label class="label" for="email">Email</label>
                        <input class="input py-2.5 text-[15px]" type="email" name="email" id="email" value="{{ old('email') }}" placeholder="ten@sangy.vn"
                               required autofocus autocomplete="username" inputmode="email" spellcheck="false"
                               @if ($failed) aria-invalid="true" aria-describedby="login-error" @endif>
                    </div>

                    <div class="mt-5">
                        <label class="label" for="password">Mật khẩu</label>
                        <div class="relative">
                            <input class="input py-2.5 pr-11 text-[15px]" type="password" name="password" id="password" required autocomplete="current-password"
                                   @if ($failed) aria-invalid="true" aria-describedby="login-error" @endif>
                            <button type="button" class="absolute inset-y-0 right-0 grid w-11 cursor-pointer place-items-center rounded-r-lg text-muted hover:text-ink"
                                    data-password-toggle="password" aria-pressed="false" aria-label="Hiện mật khẩu">
                                <x-icon name="eye" class="size-[18px]" data-state="hidden" />
                                <x-icon name="eye-off" class="size-[18px]" data-state="shown" hidden />
                            </button>
                        </div>
                    </div>

                    <label class="mt-5 flex w-fit cursor-pointer items-center gap-2.5 text-sm">
                        <input type="checkbox" name="remember" value="1" class="size-4 accent-brand" @checked(old('remember'))> Ghi nhớ đăng nhập trên máy này
                    </label>

                    <button class="btn btn-primary btn-lg mt-7 w-full">Đăng nhập</button>

                    <p class="mt-6 text-[13px] text-muted">Quên mật khẩu hoặc tài khoản bị khoá? Nhờ Admin đặt lại ở mục Người dùng.</p>
                </form>
            </div>

            <p class="px-5 pb-6 text-center text-xs text-faint sm:px-12 lg:text-left">{{ config('rma.company.name') }} · {{ config('rma.company.address') }}</p>
        </section>
    </div>
</body>
</html>
