@props(['title', 'subtitle' => null])

@php
    $nav = [
        'Nghiệp vụ' => array_values(array_filter([
            ['tickets.*', 'tickets.index', 'Phiếu RMA'],
            ['devices.*', 'devices.index', 'Thiết bị'],
            ['customers.*', 'customers.index', 'Khách hàng'],
            auth()->user()->can('admin') ? ['reports.*', 'reports.index', 'Báo cáo'] : null,
            ['flows', 'flows', 'Quy trình'],
        ])),
        'Danh mục' => [
            ['service-centers.*', 'service-centers.index', 'Hãng / TTBH'],
            ['catalog.*', 'catalog.index', 'Loại · Hãng · Model'],
        ],
        'Hệ thống' => array_values(array_filter([
            auth()->user()->can('admin') ? ['users.*', 'users.index', 'Người dùng'] : null,
            ['sequences.*', 'sequences.index', 'Bộ đếm số phiếu'],
            ['profile.*', 'profile.edit', 'Đổi mật khẩu'],
        ])),
    ];
@endphp

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} · {{ config('app.name') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen">
    <div class="min-h-screen lg:grid lg:grid-cols-[236px_1fr]">
        <aside class="flex flex-col gap-4 bg-brand-dark px-4 py-4 text-side-text lg:sticky lg:top-0 lg:h-screen lg:gap-5 lg:py-6">
            <a href="{{ route('tickets.index') }}" class="flex items-center gap-2.5 px-2">
                <img src="{{ asset('images/logo-sangy-128.png') }}" alt="{{ config('rma.company.short_name') }}" class="size-10 shrink-0">
                <span>
                    <span class="block text-[13px] font-bold tracking-[0.2em]">RMA</span>
                    <span class="block text-[11px] text-side-muted">{{ config('rma.company.short_name') }}</span>
                </span>
            </a>

            <nav class="flex gap-1 overflow-x-auto lg:flex-col lg:overflow-visible" aria-label="Menu chính">
                @foreach ($nav as $group => $items)
                    <div class="hidden px-2.5 pt-3 pb-1 text-[11px] font-semibold tracking-widest text-side-muted uppercase lg:block">{{ $group }}</div>
                    @foreach ($items as [$pattern, $route, $label])
                        <a href="{{ route($route) }}"
                           @class([
                               'rounded-md px-2.5 py-2 text-sm whitespace-nowrap hover:bg-brand-hover',
                               'bg-brand-hover font-semibold text-white' => request()->routeIs($pattern),
                           ])
                           @if (request()->routeIs($pattern)) aria-current="page" @endif>{{ $label }}</a>
                    @endforeach
                @endforeach
            </nav>

            <div class="mt-auto flex items-center justify-between gap-3 border-t border-white/10 px-2 pt-4 text-xs lg:block">
                <div>
                    <div class="font-semibold text-white">{{ auth()->user()->name }}</div>
                    <div class="text-side-muted">{{ auth()->user()->role->label() }} · {{ auth()->user()->email }}</div>
                </div>
                <form method="POST" action="{{ route('logout') }}" class="lg:mt-3">
                    @csrf
                    <button class="cursor-pointer rounded-md border border-side-muted px-3 py-1.5 text-side-text hover:bg-brand-hover">Đăng xuất</button>
                </form>
            </div>
        </aside>

        <main class="min-w-0 px-4 py-6 pb-16 lg:px-8">
            <header class="mb-5 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold text-balance">{{ $title }}</h1>
                    @if ($subtitle)
                        <p class="mt-0.5 text-muted">{{ $subtitle }}</p>
                    @endif
                </div>
                @isset($actions)
                    <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
                @endisset
            </header>

            @if (session('error'))
                <div class="mb-4 rounded-md border border-red-300 bg-red-50 px-4 py-2.5 text-sm text-red-800" role="alert">{{ session('error') }}</div>
            @endif
            @if (session('status'))
                <div class="mb-4 rounded-md border border-emerald-300 bg-emerald-50 px-4 py-2.5 text-sm text-emerald-900" role="status">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="mb-4 rounded-md border border-red-300 bg-red-50 px-4 py-2.5 text-sm text-red-800" role="alert">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            {{ $slot }}
        </main>
    </div>
</body>
</html>
