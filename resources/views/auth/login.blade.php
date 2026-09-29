<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Đăng nhập · {{ config('app.name') }}</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen">
    <div class="grid min-h-screen lg:grid-cols-2">
        <section class="relative hidden flex-col justify-between overflow-hidden bg-brand-dark p-12 text-white lg:flex"
                 style="background-image: linear-gradient(rgb(255 255 255 / .04) 1px, transparent 1px), linear-gradient(90deg, rgb(255 255 255 / .04) 1px, transparent 1px); background-size: 32px 32px;">
            <div class="flex items-center gap-3">
                <span class="grid size-10 place-items-center border border-white/40 text-sm font-bold">SY</span>
                <span class="text-sm font-bold tracking-[0.2em]">RMA</span>
            </div>
            <div>
                <p class="text-xs font-semibold tracking-[0.25em] text-side-muted uppercase">{{ config('rma.company.name') }}</p>
                <h1 class="mt-4 text-5xl leading-tight font-bold text-balance">Quản lý phiếu bảo hành và sửa chữa.</h1>
                <p class="mt-6 flex items-center gap-3 text-sm text-side-text"><span class="h-px w-10 bg-side-muted"></span>{{ config('rma.company.address') }}</p>
            </div>
        </section>

        <section class="flex items-center justify-center bg-canvas px-4 py-12">
            <form method="POST" action="{{ url('/login') }}" class="w-full max-w-sm">
                @csrf
                <p class="text-xs font-semibold tracking-[0.2em] text-muted uppercase">RMA Portal</p>
                <h2 class="mt-2 text-3xl font-bold">Đăng nhập</h2>

                @if ($errors->any())
                    <div class="mt-6 rounded-md border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-800" role="alert">{{ $errors->first() }}</div>
                @endif

                <div class="mt-6">
                    <label class="label" for="email">Địa chỉ email</label>
                    <input class="input py-3" type="email" name="email" id="email" value="{{ old('email') }}" placeholder="ten@sangy.vn" required autofocus autocomplete="username">
                </div>
                <div class="mt-4">
                    <label class="label" for="password">Mật khẩu</label>
                    <input class="input py-3" type="password" name="password" id="password" placeholder="Nhập mật khẩu" required autocomplete="current-password">
                </div>
                <label class="mt-4 flex items-center gap-2 text-sm">
                    <input type="checkbox" name="remember" value="1" class="size-4 accent-brand"> Ghi nhớ đăng nhập
                </label>
                <button class="btn btn-primary mt-6 w-full py-3">Đăng nhập</button>
            </form>
        </section>
    </div>
</body>
</html>
