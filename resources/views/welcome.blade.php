<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="ParkFlow smart parking management with staff-assisted ANPR entry, QR tickets, live parking information and transparent billing.">
    <title>ParkFlow · Smart Parking Management</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen overflow-x-hidden bg-[#f8faf9] text-[#102a23]">
<a href="#main" class="sr-only z-[60] rounded-lg bg-white px-4 py-2 font-semibold text-[#064e3b] focus:not-sr-only focus:fixed focus:left-4 focus:top-4">Skip to content</a>

<header class="sticky top-0 z-50 border-b border-emerald-950/10 bg-white/95 shadow-sm backdrop-blur-xl">
    <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-4 py-3 sm:px-6 lg:flex-nowrap lg:px-8">
        <a href="#home" class="flex items-center gap-3 text-[#102a23] hover:text-[#102a23]" aria-label="ParkFlow home">
            <span class="grid h-11 w-11 place-items-center rounded-xl bg-gradient-to-br from-emerald-400 to-[#087f46] text-2xl font-black text-white shadow-lg shadow-emerald-900/15">P</span>
            <span><span class="block text-xl font-black leading-none tracking-tight">ParkFlow<sup class="text-[8px]">®</sup></span><span class="mt-0.5 block text-[10px] uppercase tracking-[0.16em] text-slate-500">Smart Parking</span></span>
        </a>

        <nav class="order-3 flex w-full items-center gap-1 overflow-x-auto pb-1 text-sm font-semibold text-slate-600 lg:order-2 lg:w-auto lg:overflow-visible lg:pb-0" aria-label="Primary navigation">
            <a href="#home" class="shrink-0 rounded-lg px-3 py-2 text-[#087f46] hover:bg-emerald-50">Home</a>
            <a href="#features" class="shrink-0 rounded-lg px-3 py-2 hover:bg-emerald-50 hover:text-[#087f46]">Features</a>
            <a href="#how-it-works" class="shrink-0 rounded-lg px-3 py-2 hover:bg-emerald-50 hover:text-[#087f46]">How It Works</a>
            <a href="#parking-information" class="shrink-0 rounded-lg px-3 py-2 hover:bg-emerald-50 hover:text-[#087f46]">Parking Information</a>
        </nav>

        <div class="order-2 flex items-center gap-2 lg:order-3">
            @auth
                <a href="{{ url('/dashboard') }}" class="btn-primary">Open dashboard</a>
            @else
                <a href="{{ route('login') }}" class="btn-secondary hidden sm:inline-flex">Sign In</a>
                @if (Route::has('register'))
                    <a href="{{ route('register') }}" class="btn-primary"><span class="hidden sm:inline">Create Customer </span>Account</a>
                @endif
            @endauth
        </div>
    </div>
</header>

<main id="main">
    <section id="home" class="relative isolate overflow-hidden bg-[#022c22] text-white">
        <div class="pointer-events-none absolute -left-32 top-16 h-80 w-80 rounded-full bg-emerald-400/10 blur-3xl"></div>
        <div class="pointer-events-none absolute -right-24 top-0 h-96 w-96 rounded-full border-[44px] border-emerald-400/[0.06]"></div>
        <div class="mx-auto grid max-w-7xl items-center gap-12 px-4 py-14 sm:px-6 sm:py-20 lg:grid-cols-[.9fr_1.1fr] lg:px-8 lg:py-24">
            <div class="relative z-10">
                <div class="mb-6 flex flex-wrap gap-2 text-xs font-bold text-emerald-100">
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-300/20 bg-emerald-400/10 px-3 py-1.5"><x-icon name="camera" class="h-4 w-4 text-emerald-300"/>ANPR-Assisted</span>
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-300/20 bg-emerald-400/10 px-3 py-1.5"><x-icon name="receipt" class="h-4 w-4 text-emerald-300"/>QR Tickets</span>
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-300/20 bg-emerald-400/10 px-3 py-1.5"><x-icon name="shield" class="h-4 w-4 text-emerald-300"/>Secure Operations</span>
                </div>
                <h1 class="max-w-2xl text-4xl font-black leading-[1.03] tracking-tight !text-white sm:text-5xl lg:text-6xl">Complete Smart<br><span class="text-emerald-300">Parking Management</span></h1>
                <p class="mt-6 max-w-xl text-base leading-7 text-white/75 sm:text-lg">One connected experience for staff-assisted ANPR Check-In, secure QR parking tickets, live parking monitoring and transparent billing.</p>
                <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                    @auth
                        <a href="{{ url('/dashboard') }}" class="btn-lg inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-500 px-6 font-bold text-white shadow-xl shadow-emerald-950/30 transition hover:-translate-y-0.5 hover:bg-emerald-400 hover:text-white"><x-icon name="grid"/>Open dashboard</a>
                    @else
                        <a href="{{ route('login') }}" class="btn-lg inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-500 px-6 font-bold text-white shadow-xl shadow-emerald-950/30 transition hover:-translate-y-0.5 hover:bg-emerald-400 hover:text-white"><x-icon name="arrow-in"/>Sign In</a>
                        @if (Route::has('register'))<a href="{{ route('register') }}" class="btn-lg inline-flex items-center justify-center gap-2 rounded-xl border border-white/25 bg-white/10 px-6 font-bold text-white backdrop-blur-sm transition hover:bg-white/15 hover:text-white"><x-icon name="user"/>Create Customer Account</a>@endif
                    @endauth
                </div>
                <div class="mt-8 grid max-w-xl grid-cols-3 gap-3 border-t border-white/10 pt-6 text-xs text-white/60 sm:text-sm">
                    <span><strong class="block text-lg text-white">Staff</strong>verified entry</span>
                    <span><strong class="block text-lg text-white">Live</strong>parking status</span>
                    <span><strong class="block text-lg text-white">LKR</strong>clear billing</span>
                </div>
            </div>

            <div class="relative z-10 mx-auto w-full max-w-2xl lg:mx-0">
                <div class="absolute -inset-5 rounded-[2.5rem] bg-emerald-400/10 blur-2xl"></div>
                <div class="relative overflow-hidden rounded-[2rem] border border-white/15 bg-[#064e3b] p-2 shadow-[0_35px_90px_rgb(0_0_0/0.38)] sm:p-3">
                    <img src="{{ asset('images/auth/parkflow-gate-hero.png') }}" alt="White car approaching a staff-operated ParkFlow entrance with an ANPR camera and barrier gate" width="1199" height="1312" class="h-auto w-full rounded-[1.5rem]" fetchpriority="high">
                    <div class="absolute bottom-6 right-6 max-w-[13rem] rounded-2xl border border-emerald-300/60 bg-[#033d30]/90 p-3 text-white shadow-2xl backdrop-blur-md sm:bottom-9 sm:right-9 sm:p-4">
                        <div class="flex items-center gap-2 text-xs font-bold text-emerald-200"><span class="grid h-5 w-5 place-items-center rounded-full bg-emerald-400 text-[#022c22]"><x-icon name="check" class="h-3 w-3"/></span>Plate Recognized</div>
                        <div class="mt-2 rounded-lg bg-white px-3 py-2 text-center font-black tracking-wider text-[#102a23]">WP CAB 1234</div>
                        <p class="mt-2 text-[11px] text-white/65">Staff verification required before Check-In</p>
                    </div>
                </div>
                <div class="absolute -bottom-5 left-5 flex items-center gap-3 rounded-2xl border border-emerald-200 bg-white px-4 py-3 text-[#102a23] shadow-xl sm:left-10">
                    <span class="grid h-10 w-10 place-items-center rounded-xl bg-emerald-50 text-[#087f46]"><x-icon name="receipt"/></span><span><strong class="block text-sm">QR Ticket Ready</strong><span class="block text-xs text-slate-500">Secure parking-session reference</span></span>
                </div>
            </div>
        </div>
    </section>

    <section id="features" class="scroll-mt-24 bg-[#f8faf9] py-20 sm:py-24">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-3xl text-center">
                <p class="text-sm font-bold uppercase tracking-[0.2em] text-[#087f46]">Connected parking operations</p>
                <h2 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">Everything needed for a clearer parking journey</h2>
                <p class="mt-4 text-base leading-7 text-slate-600">Purpose-built tools connect customer information with staff-operated gate workflows and auditable parking records.</p>
            </div>
            <div class="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ([
                    ['camera','ANPR-Assisted Vehicle Entry','Recognition results support staff verification at the gate, with manual handling available when needed.'],
                    ['receipt','QR Parking Tickets','Successful Check-In produces a secure QR ticket linked to the parking session.'],
                    ['grid','Live Parking Availability','Operational teams can monitor available capacity before assigning an appropriate parking zone.'],
                    ['calendar','Flexible Parking Packages','Published daily, weekly and longer-term package information helps customers understand their options.'],
                    ['calculator','Transparent Billing','Server-calculated charges use configured rates and produce a clear billing breakdown in LKR.'],
                    ['clock','Parking History & Receipts','Customers can review completed sessions and access their existing receipts from one account.'],
                ] as [$icon,$heading,$copy])
                    <article class="group rounded-2xl border border-emerald-950/10 bg-white p-6 shadow-[0_10px_35px_rgb(2_44_34/0.06)] transition hover:-translate-y-1 hover:border-emerald-200 hover:shadow-[0_18px_45px_rgb(2_44_34/0.10)]">
                        <span class="grid h-12 w-12 place-items-center rounded-xl bg-emerald-50 text-[#087f46] transition group-hover:bg-[#087f46] group-hover:text-white"><x-icon :name="$icon" class="h-6 w-6"/></span>
                        <h3 class="mt-5 text-lg font-bold">{{ $heading }}</h3><p class="mt-2 text-sm leading-6 text-slate-600">{{ $copy }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section id="how-it-works" class="scroll-mt-24 bg-white py-20 sm:py-24">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="max-w-3xl"><p class="text-sm font-bold uppercase tracking-[0.2em] text-[#087f46]">How ParkFlow works</p><h2 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">From account creation to a complete receipt</h2><p class="mt-4 text-base leading-7 text-slate-600">Customers manage their information online while trained staff remain responsible for physical Check-In and parking-zone assignment.</p></div>
            <ol class="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ([
                    ['user','Create Customer Account','Create a customer profile using the public registration page.'],
                    ['car','Register Vehicle','Add the registration number and vehicle information to your account.'],
                    ['gate','Staff Check-In at Gate','Staff verify the vehicle and assign a suitable zone after checking capacity.'],
                    ['receipt','Receive QR Parking Ticket','A QR ticket is generated after a successful staff-operated Check-In.'],
                    ['clock','Track Active Parking','Review authorized active-session details from the customer portal.'],
                    ['check','Check-Out & View Receipt','Staff complete Check-Out and the customer can access the resulting receipt.'],
                ] as $index => [$icon,$heading,$copy])
                    <li class="relative rounded-2xl border border-emerald-100 bg-gradient-to-br from-white to-emerald-50/60 p-6">
                        <span class="absolute right-5 top-4 text-4xl font-black text-emerald-100">{{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}</span>
                        <span class="grid h-11 w-11 place-items-center rounded-xl bg-[#064e3b] text-emerald-200"><x-icon :name="$icon"/></span><h3 class="mt-5 pr-10 text-base font-bold">{{ $heading }}</h3><p class="mt-2 text-sm leading-6 text-slate-600">{{ $copy }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    <section id="parking-information" class="scroll-mt-24 bg-[#ecfdf5] py-20 sm:py-24">
        <div class="mx-auto grid max-w-7xl items-center gap-12 px-4 sm:px-6 lg:grid-cols-[.9fr_1.1fr] lg:px-8">
            <div>
                <p class="text-sm font-bold uppercase tracking-[0.2em] text-[#087f46]">Customer benefits</p>
                <h2 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">The parking information you need, without the guesswork</h2>
                <p class="mt-4 text-base leading-7 text-slate-600">Registered customers can access their own authorized vehicle and parking information while operational control stays with parking staff.</p>
                <div class="mt-8 flex flex-wrap gap-3">
                    @guest<a href="{{ route('login') }}" class="btn-primary btn-lg">Sign In</a>@if(Route::has('register'))<a href="{{ route('register') }}" class="btn-secondary btn-lg">Create Customer Account</a>@endif
                    @else<a href="{{ url('/dashboard') }}" class="btn-primary btn-lg">Open dashboard</a>@endguest
                </div>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach ([
                    ['car','Vehicle management','Register and maintain vehicles associated with your customer account.'],
                    ['pulse','Active parking','See authorized details when one of your vehicles is currently parked.'],
                    ['tag','Published prices','Review available parking categories, packages and published prices.'],
                    ['clock','Session history','Review past parking visits associated with your account.'],
                    ['receipt','Receipts','Access available receipt records for completed parking sessions.'],
                    ['chat','Account support','Use authenticated support tools when assistance from the parking office is needed.'],
                ] as [$icon,$heading,$copy])
                    <div class="flex gap-4 rounded-2xl border border-emerald-200/80 bg-white p-5 shadow-sm"><span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-emerald-50 text-[#087f46]"><x-icon :name="$icon"/></span><span><strong class="block text-sm">{{ $heading }}</strong><span class="mt-1 block text-xs leading-5 text-slate-600">{{ $copy }}</span></span></div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="bg-[#064e3b] px-4 py-16 text-center text-white sm:px-6">
        <div class="mx-auto max-w-3xl"><h2 class="text-3xl font-black !text-white sm:text-4xl">A smarter way to understand your parking</h2><p class="mx-auto mt-4 max-w-2xl leading-7 text-white/70">Create a customer account to register vehicles, review published parking information and access your authorized parking records.</p>
            <div class="mt-7 flex flex-col justify-center gap-3 sm:flex-row">@guest<a href="{{ route('login') }}" class="btn-lg rounded-xl bg-white px-6 font-bold text-[#064e3b] hover:bg-emerald-50 hover:text-[#064e3b]">Sign In</a>@if(Route::has('register'))<a href="{{ route('register') }}" class="btn-lg rounded-xl border border-white/25 bg-white/10 px-6 font-bold text-white hover:bg-white/15 hover:text-white">Create Customer Account</a>@endif @else<a href="{{ url('/dashboard') }}" class="btn-lg rounded-xl bg-white px-6 font-bold text-[#064e3b] hover:bg-emerald-50 hover:text-[#064e3b]">Open dashboard</a>@endguest</div>
        </div>
    </section>
</main>

<footer class="bg-[#022c22] text-white">
    <div class="mx-auto flex max-w-7xl flex-col gap-8 px-4 py-10 sm:px-6 md:flex-row md:items-end md:justify-between lg:px-8">
        <div><div class="flex items-center gap-3"><span class="grid h-10 w-10 place-items-center rounded-xl bg-emerald-500 text-xl font-black">P</span><span class="text-xl font-black">ParkFlow</span></div><p class="mt-3 max-w-md text-sm leading-6 text-white/55">Smart parking management for safer, clearer customer and operational journeys.</p></div>
        <div class="md:text-right"><nav class="flex flex-wrap gap-4 text-sm font-semibold" aria-label="Footer navigation">@guest<a href="{{ route('login') }}" class="text-white/75 hover:text-white">Sign In</a>@if(Route::has('register'))<a href="{{ route('register') }}" class="text-white/75 hover:text-white">Customer Registration</a>@endif @else<a href="{{ url('/dashboard') }}" class="text-white/75 hover:text-white">Dashboard</a>@endguest</nav><p class="mt-3 text-xs text-white/45">Times are shown in Sri Lanka time (Asia/Colombo).</p><p class="mt-1 text-xs text-white/45">© {{ now()->year }} ParkFlow. Smart Parking Management.</p></div>
    </div>
</footer>
</body>
</html>
