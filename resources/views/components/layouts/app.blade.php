@props(['title', 'subtitle' => null, 'parent' => null])

{{--
    App shell. $parent = [label, url] adds a link back to the list above the title.
    A page may pass its own $header slot (the ticket tag does) instead of the title block.
--}}
@php
    $user = auth()->user();
    $nav = [
        'Công việc' => [
            ['tickets.*', 'tickets.index', 'Phiếu RMA', 'ticket'],
            ['devices.*', 'devices.index', 'Thiết bị', 'monitor'],
            ['customers.*', 'customers.index', 'Khách hàng', 'building'],
        ],
        'Theo dõi' => array_values(array_filter([
            $user->can('admin') ? ['reports.*', 'reports.index', 'Báo cáo', 'chart'] : null,
            ['flows', 'flows', 'Quy trình xử lý', 'route'],
        ])),
        'Danh mục' => [
            ['service-centers.*', 'service-centers.index', 'Hãng / TTBH', 'wrench'],
            ['catalog.*', 'catalog.index', 'Loại, hãng, model', 'layers'],
        ],
        'Hệ thống' => array_values(array_filter([
            $user->can('admin') ? ['users.*', 'users.index', 'Người dùng', 'users'] : null,
            ['sequences.*', 'sequences.index', 'Bộ đếm số phiếu', 'hash'],
        ])),
    ];
    $initials = collect(preg_split('/\s+/u', trim($user->name)))->filter()->map(fn ($part) => mb_substr($part, 0, 1))->pipe(fn ($parts) => $parts->count() > 1 ? $parts->first().$parts->last() : $parts->first());
@endphp

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} · {{ config('app.name') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">
    {{-- Apply the remembered sidebar width before first paint, so the page does not jump. --}}
    <script>try { if (localStorage.getItem('sidebar') === 'collapsed') document.documentElement.dataset.sidebar = 'collapsed'; } catch {}</script>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-50 focus:rounded-md focus:bg-white focus:px-3 focus:py-2 focus:font-semibold focus:shadow">Bỏ qua menu</a>

    {{-- Phone / tablet top bar --}}
    <header class="sticky top-0 z-30 flex h-14 items-center gap-2 border-b border-white/10 bg-brand-dark px-2 text-side-text lg:hidden">
        <button type="button" class="btn btn-icon border-transparent bg-transparent text-white hover:bg-side-hover" data-nav-open aria-controls="sidebar" aria-expanded="false" aria-label="Mở menu">
            <x-icon name="menu" />
        </button>
        <a href="{{ route('tickets.index') }}" class="flex items-center gap-2">
            <img src="{{ asset('images/logo-sangy-128.png') }}" alt="" class="size-8">
            <span class="text-sm font-bold tracking-[0.14em] text-white">RMA</span>
        </a>
        <a href="{{ route('tickets.create') }}" class="btn btn-sm ml-auto border-white/20 bg-white/10 text-white hover:bg-white/20">
            <x-icon name="plus" /> Lập phiếu
        </a>
    </header>

    <div class="app-shell min-h-screen lg:grid lg:grid-cols-[15.5rem_1fr]">
        <div class="fixed inset-0 z-40 bg-ink/50 lg:hidden" data-nav-close hidden></div>

        <aside id="sidebar" class="sidebar fixed inset-y-0 left-0 z-50 flex w-72 max-w-[85vw] -translate-x-full flex-col bg-brand-dark text-side-text transition-transform duration-200 lg:sticky lg:top-0 lg:z-auto lg:h-screen lg:w-auto lg:max-w-none lg:translate-x-0" aria-label="Menu chính">
            <div class="flex h-16 shrink-0 items-center gap-2 px-4">
                <a href="{{ route('tickets.index') }}" class="flex min-w-0 items-center gap-2.5" title="Về danh sách phiếu">
                    <img src="{{ asset('images/logo-sangy-128.png') }}" alt="{{ config('rma.company.short_name') }}" class="size-9 shrink-0">
                    <span class="sidebar-label min-w-0 leading-tight">
                        <span class="block text-[13px] font-bold tracking-[0.18em] text-white">RMA</span>
                        <span class="block truncate text-xs text-side-muted">Bảo hành · Sửa chữa</span>
                    </span>
                </a>
                <button type="button" class="ml-auto grid size-9 shrink-0 cursor-pointer place-items-center rounded-md text-side-muted hover:bg-side-hover hover:text-white lg:hidden" data-nav-close aria-label="Đóng menu">
                    <x-icon name="x" />
                </button>
            </div>

            <div class="px-3 pt-1">
                <a href="{{ route('tickets.create') }}" @class(['sidebar-cta flex items-center justify-center gap-2 rounded-lg bg-white px-3 py-2 text-sm font-semibold text-brand-dark transition-colors hover:bg-brand-soft', 'ring-2 ring-[#8fd1ad]' => request()->routeIs('tickets.create')]) title="Lập phiếu mới">
                    <x-icon name="plus" class="size-4 shrink-0" /><span class="sidebar-label">Lập phiếu mới</span>
                </a>
            </div>

            <nav class="min-h-0 flex-1 overflow-y-auto px-3 pb-4" aria-label="Chức năng">
                @foreach ($nav as $group => $items)
                    @continue($items === [])
                    <div class="nav-group sidebar-label">{{ $group }}</div>
                    <div class="sidebar-rule hidden"></div>
                    <ul class="space-y-0.5">
                        @foreach ($items as [$pattern, $route, $label, $icon])
                            @php $current = request()->routeIs($pattern) && ! request()->routeIs('tickets.create'); @endphp
                            <li>
                                <a href="{{ route($route) }}" class="nav-item" title="{{ $label }}" @if ($current) aria-current="page" @endif>
                                    <x-icon :name="$icon" /><span class="sidebar-label">{{ $label }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endforeach
            </nav>

            <div class="shrink-0 px-3 pb-1 max-lg:hidden">
                <button type="button" class="nav-item w-full cursor-pointer text-side-muted" data-sidebar-toggle aria-expanded="true" title="Thu gọn menu">
                    <x-icon name="sidebar" /><span class="sidebar-label">Thu gọn menu</span>
                </button>
            </div>

            <details class="account relative shrink-0 border-t border-white/10 p-3" data-menu>
                <summary class="flex cursor-pointer list-none items-center gap-2.5 rounded-lg p-1.5 hover:bg-side-hover [&::-webkit-details-marker]:hidden" title="{{ $user->name }}">
                    <span class="grid size-8 shrink-0 place-items-center rounded-full bg-[#8fd1ad] text-xs font-bold text-brand-dark" aria-hidden="true">{{ mb_strtoupper($initials) }}</span>
                    <span class="sidebar-label min-w-0 flex-1 leading-tight">
                        <span class="block truncate text-[13px] font-semibold text-white">{{ $user->name }}</span>
                        <span class="block truncate text-xs text-side-muted">{{ $user->role->label() }}</span>
                    </span>
                    <x-icon name="chevrons-up-down" class="sidebar-label size-4 shrink-0 text-side-muted" />
                </summary>
                <div class="menu bottom-full left-3 mb-1">
                    <div class="border-b border-line px-3 pt-1.5 pb-2.5">
                        <div class="font-semibold">{{ $user->name }}</div>
                        <div class="truncate text-xs text-muted">{{ $user->email }}</div>
                    </div>
                    <a href="{{ route('profile.edit') }}" @if (request()->routeIs('profile.*')) aria-current="page" @endif><x-icon name="lock" />Đổi mật khẩu</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button><x-icon name="log-out" />Đăng xuất</button>
                    </form>
                </div>
            </details>
        </aside>

        <main id="main" class="min-w-0 px-4 pt-5 pb-16 sm:px-6 lg:px-8 lg:pt-7" tabindex="-1">
            @isset($header)
                {{ $header }}
            @else
                <header class="mb-6 flex flex-wrap items-end justify-between gap-x-4 gap-y-3">
                    <div class="min-w-0">
                        @if ($parent)
                            <a href="{{ $parent[1] }}" class="mb-1.5 inline-flex items-center gap-1 text-[13px] font-medium text-muted hover:text-brand">
                                <x-icon name="chevron-left" class="size-4" />{{ $parent[0] }}
                            </a>
                        @endif
                        <h1 class="text-2xl leading-tight font-semibold tracking-tight text-balance">{{ $title }}</h1>
                        @if ($subtitle)
                            <p class="mt-1 max-w-3xl text-muted">{{ $subtitle }}</p>
                        @endif
                    </div>
                    @isset($actions)
                        <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
                    @endisset
                </header>
            @endisset

            <div class="space-y-2 empty:hidden [&:not(:empty)]:mb-5">
                @if (session('error'))
                    <div class="alert alert-error" role="alert" data-flash><x-icon name="alert" /><p class="flex-1">{{ session('error') }}</p><button type="button" class="-my-0.5 cursor-pointer rounded px-1 opacity-60 hover:opacity-100" data-dismiss aria-label="Ẩn thông báo"><x-icon name="x" class="size-4" /></button></div>
                @endif
                @if (session('status'))
                    <div class="alert alert-success" role="status" data-flash><x-icon name="check-circle" /><p class="flex-1">{{ session('status') }}</p><button type="button" class="-my-0.5 cursor-pointer rounded px-1 opacity-60 hover:opacity-100" data-dismiss aria-label="Ẩn thông báo"><x-icon name="x" class="size-4" /></button></div>
                @endif
                @if ($errors->any())
                    <div class="alert alert-error" role="alert">
                        <x-icon name="alert" />
                        <div class="flex-1">
                            @if ($errors->count() > 1)
                                <p class="font-semibold">Chưa lưu được, cần sửa {{ $errors->count() }} chỗ:</p>
                                <ul class="mt-1 list-disc space-y-0.5 pl-4">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            @else
                                <p>{{ $errors->first() }}</p>
                            @endif
                        </div>
                    </div>
                @endif
            </div>

            {{ $slot }}
        </main>
    </div>

    {{-- Shared confirmation for forms with data-confirm (see app.js). --}}
    <dialog id="confirm-dialog" class="modal max-w-md" aria-labelledby="confirm-dialog-text">
        <div class="modal-body">
            <div class="flex gap-3">
                <span class="grid size-9 shrink-0 place-items-center rounded-full bg-amber-100 text-amber-800" data-confirm-icon><x-icon name="warning" class="size-[18px]" /></span>
                <p id="confirm-dialog-text" class="pt-1.5 text-[15px] leading-relaxed" data-confirm-text></p>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-secondary" data-dialog-close>Quay lại</button>
                <button type="button" class="btn btn-primary" data-confirm-accept>Đồng ý</button>
            </div>
        </div>
    </dialog>
</body>
</html>
