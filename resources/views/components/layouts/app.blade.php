@props(['title' => 'ParkFlow', 'crumbs' => [], 'wide' => false])
@php
    $user = auth()->user();
    $nav = \App\Support\Frontend\Navigation::for($user);
    $isOps = $user->hasAnyRole('staff', 'admin', 'super_admin');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} · ParkFlow</title>
    <script>window.__PARKFLOW__ = @js(['user' => (new \App\Http\Resources\UserResource($user))->resolve()]);</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen" x-data="{ navOpen: false }" @keydown.escape.window="navOpen = false">
<a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-3 focus:top-3 focus:z-[100] btn-primary">Skip to content</a>

{{-- Sidebar --}}
<div x-show="navOpen" x-cloak x-transition.opacity class="fixed inset-0 z-30 bg-navy-deep/60 lg:hidden" @click="navOpen = false" aria-hidden="true"></div>
<aside id="sidebar" class="fixed inset-y-0 left-0 z-40 flex w-72 -translate-x-full transform flex-col overflow-hidden bg-navy-deep text-white shadow-2xl transition-transform lg:translate-x-0"
       :class="navOpen && 'translate-x-0'" aria-label="Main navigation">
    <div class="flex h-20 items-center justify-between gap-2 border-b border-white/10 px-5">
        <a href="{{ route(\App\Support\Frontend\Navigation::dashboardRoute($user)) }}" class="flex items-center gap-2.5 text-white hover:text-white">
            <span class="grid h-10 w-10 place-items-center rounded-full bg-gradient-to-br from-brand to-brand-strong text-xl font-black text-white shadow-lg" aria-hidden="true">P</span>
            <span class="text-xl font-bold tracking-tight">ParkFlow</span>
        </a>
        <button type="button" class="rounded-md p-2 text-white/80 hover:bg-white/10 lg:hidden" @click="navOpen = false" aria-label="Close menu"><x-icon name="x"/></button>
    </div>
    <nav class="min-h-0 flex-1 overflow-y-auto px-3 pb-4" x-data="{ remembered: localStorage.getItem('pf.nav.group') }">
        @foreach ($nav as $group)
            @php
                $groupKey = \Illuminate\Support\Str::slug($group['label']);
                $groupActive = collect($group['items'])->contains(fn ($item) => request()->routeIs($item['route']) || request()->routeIs($item['route'].'.*'));
            @endphp
            <div x-data="{ open: {{ $groupActive ? 'true' : 'false' }} || remembered === '{{ $groupKey }}' }">
                <button type="button" class="nav-group flex w-full items-center justify-between hover:text-white" @click="open = !open" :aria-expanded="open.toString()">
                    <span>{{ $group['label'] }}</span>
                    <x-icon name="chevron-down" class="h-3.5 w-3.5 transition-transform" x-bind:class="!open && '-rotate-90'"/>
                </button>
                <ul x-show="open" x-transition.opacity.duration.150ms class="space-y-0.5" @click="localStorage.setItem('pf.nav.group', '{{ $groupKey }}')">
                    @foreach ($group['items'] as $item)
                        @php $active = request()->routeIs($item['route']) || request()->routeIs($item['route'].'.*'); @endphp
                        <li><a href="{{ route($item['route']) }}" class="nav-link" @if($active) aria-current="page" @endif>
                            <x-icon :name="$item['icon']" class="h-[19px] w-[19px] shrink-0 {{ $active ? 'text-white' : 'text-white/65' }}"/>{{ $item['label'] }}</a></li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </nav>
    <div class="shrink-0 border-t border-white/10 bg-navy-deep px-5 py-4">
        <p class="text-sm font-bold text-white">ParkFlow</p><p class="text-xs text-white/55">Smart Parking Management</p>
    </div>
</aside>

<div class="lg:pl-72">
    {{-- Header --}}
    <header role="banner" class="sticky top-0 z-20 flex h-20 items-center gap-3 border-b border-line bg-surface/95 px-4 shadow-[0_1px_10px_rgb(16_42_35/0.03)] backdrop-blur sm:px-6 lg:px-8">
        <button type="button" class="-ml-1 rounded-md p-2 text-ink hover:bg-canvas lg:hidden" @click="navOpen = true" aria-label="Open menu" aria-controls="sidebar" :aria-expanded="navOpen.toString()"><x-icon name="menu"/></button>
        <nav aria-label="Breadcrumb" class="min-w-0 flex-1">
            <ol class="flex min-w-0 items-center gap-1.5 text-sm">
                <li class="hidden sm:block"><a href="{{ route(\App\Support\Frontend\Navigation::dashboardRoute($user)) }}" class="text-ink-muted hover:text-ink">Home</a></li>
                @foreach ($crumbs as $label => $url)
                    <li class="hidden items-center gap-1.5 sm:flex"><x-icon name="chevron-right" class="h-3.5 w-3.5 text-ink-faint"/><a href="{{ $url }}" class="text-ink-muted hover:text-ink">{{ $label }}</a></li>
                @endforeach
                <li class="flex min-w-0 items-center gap-1.5"><x-icon name="chevron-right" class="hidden h-3.5 w-3.5 text-ink-faint sm:block"/><span class="truncate font-semibold text-ink" aria-current="page">{{ $title }}</span></li>
            </ol>
        </nav>
        <div class="flex items-center gap-1 sm:gap-3">
            @if ($isOps)
                <div x-data="engineStatus()" class="hidden md:block">
                    <span class="chip" :class="cls" :title="title" x-text="label" role="status" aria-live="polite">ANPR …</span>
                </div>
            @endif
            <div x-data="clock()" class="hidden text-right text-xs leading-tight text-ink-muted sm:block" aria-label="Current time in Sri Lanka">
                <div class="font-semibold text-ink tabular-nums" x-text="t"></div><div x-text="d"></div>
            </div>
            <a href="{{ route('notifications') }}" x-data="unreadBadge()" class="relative rounded-full p-2.5 text-brand-strong hover:bg-brand-faint" :aria-label="count ? `Notifications, ${count} unread` : 'Notifications'">
                <x-icon name="bell"/><span x-show="count" x-cloak class="absolute right-1 top-1 grid h-4 min-w-4 place-items-center rounded-full bg-danger-ink px-1 text-[10px] font-bold text-white" x-text="count > 9 ? '9+' : count"></span>
            </a>
            <div x-data="{ open: false }" class="relative" @click.outside="open = false" @keydown.escape="open = false">
                <button type="button" class="flex items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-canvas" @click="open = !open" :aria-expanded="open.toString()" aria-haspopup="menu">
                    <span class="grid h-10 w-10 place-items-center rounded-full border border-brand-soft bg-brand-faint text-sm font-bold text-brand-stronger" aria-hidden="true">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                    <span class="hidden text-left text-sm leading-tight md:block"><span class="block font-semibold text-ink">{{ $user->name }}</span><span class="block text-xs text-ink-muted">{{ \Illuminate\Support\Str::headline($user->role) }}</span></span>
                    <x-icon name="chevron-down" class="hidden h-4 w-4 text-ink-muted md:block"/>
                </button>
                <div x-show="open" x-cloak x-transition.origin.top.right class="absolute right-0 mt-2 w-56 rounded-xl border border-line bg-surface p-1.5 shadow-[var(--shadow-pop)]" role="menu">
                    <div class="px-3 py-2 text-xs text-ink-muted md:hidden">{{ $user->name }} · {{ \Illuminate\Support\Str::headline($user->role) }}</div>
                    <a href="{{ route('account') }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-ink hover:bg-canvas" role="menuitem"><x-icon name="user" class="h-4 w-4"/>My account</a>
                    <form method="POST" action="{{ route('logout') }}">@csrf
                        <button type="submit" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm text-danger-ink hover:bg-danger" role="menuitem"><x-icon name="logout" class="h-4 w-4"/>Sign out</button>
                    </form>
                </div>
            </div>
        </div>
    </header>

    <main id="main" tabindex="-1" class="mx-auto w-full {{ $wide ? 'max-w-[1680px]' : 'max-w-[1440px]' }} px-4 py-6 sm:px-6 lg:px-8 lg:py-7 focus:outline-none">
        @if (session('status'))<div class="alert-success mb-4" role="status">{{ session('status') }}</div>@endif
        {{ $slot }}
    </main>
</div>

@include('partials.overlays')
</body>
</html>
