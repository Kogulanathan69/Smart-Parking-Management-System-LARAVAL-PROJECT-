@props(['title' => 'Sign in', 'subtitle' => null])
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · ParkFlow</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="pf-login min-h-screen bg-[#f8faf9]">
<div class="grid min-h-screen lg:grid-cols-[minmax(36rem,1.18fr)_minmax(31rem,.82fr)] 2xl:grid-cols-[minmax(48rem,1.25fr)_minmax(38rem,.75fr)]">
    <section class="pf-hero relative hidden h-screen overflow-hidden bg-[#022c22] text-white lg:flex lg:flex-col">
        <div class="pointer-events-none absolute inset-x-0 top-0 z-10 h-[48%] bg-gradient-to-b from-[#022c22] via-[#02382b]/95 to-transparent"></div>
        <div class="pointer-events-none absolute -right-16 -top-8 z-10 h-80 w-80 rounded-full border-[34px] border-emerald-400/[0.08]"></div>

        <header class="pf-header relative z-30 flex items-start justify-between gap-6 px-8 pt-5 xl:px-10 xl:pt-6 2xl:px-12 2xl:pt-9">
            <a href="{{ url('/') }}" class="flex shrink-0 items-center gap-3 text-white hover:text-white" aria-label="ParkFlow home">
                <span class="grid h-12 w-12 place-items-center rounded-xl bg-gradient-to-br from-emerald-400 to-[#087f46] text-2xl font-black shadow-[0_12px_30px_rgb(16_185_129/0.25)] ring-1 ring-white/15 2xl:h-14 2xl:w-14 2xl:text-3xl">P</span>
                <span><span class="block text-2xl font-black tracking-tight 2xl:text-3xl">ParkFlow<sup class="text-xs">®</sup></span><span class="block text-[10px] tracking-wide text-white/70 2xl:text-xs">Smart Parking Management</span></span>
            </a>
            <div class="mt-2 hidden items-center gap-4 text-xs font-bold text-white/90 xl:flex" aria-label="Platform capabilities">
                <span class="flex items-center gap-2"><x-icon name="camera" class="h-4 w-4 text-emerald-400"/>ANPR</span><span class="h-5 w-px bg-white/25"></span>
                <span class="flex items-center gap-2"><x-icon name="receipt" class="h-4 w-4 text-emerald-400"/>QR Ticket</span><span class="h-5 w-px bg-white/25"></span>
                <span class="flex items-center gap-2"><x-icon name="shield" class="h-4 w-4 text-emerald-400"/>Secure</span><span class="h-5 w-px bg-white/25"></span>
                <span class="flex items-center gap-2"><x-icon name="check" class="h-4 w-4 text-emerald-400"/>Audited</span>
            </div>
        </header>

        <div class="pf-intro relative z-30 px-8 pt-5 xl:px-10 xl:pt-6 2xl:px-12 2xl:pt-10">
            <h2 class="max-w-3xl text-3xl font-black leading-[1.02] tracking-tight !text-white xl:text-4xl 2xl:text-6xl">Complete Smart<br><span class="text-emerald-300">Parking Management</span></h2>
            <p class="mt-3 max-w-2xl text-sm leading-5 text-white/80 2xl:mt-4 2xl:text-base 2xl:leading-6">ANPR-powered vehicle entry, real-time parking operations,<br class="hidden xl:block"> secure payments, and full audit control—all in one platform.</p>
        </div>

        <div class="pf-features" aria-label="Parking platform features">
            @foreach ([['camera','ANPR Recognition','Automatic plate capture'],['shield','Secure Parking','Monitored & auditable'],['grid','QR Check-In','Digital entry & exit'],['chart','Real-time Insights','Occupancy & revenue']] as [$icon,$label,$detail])
                <div><span><x-icon :name="$icon"/></span><p><strong>{{ $label }}</strong><small>{{ $detail }}</small></p></div>
            @endforeach
        </div>

        <div class="pf-art absolute inset-x-0 bottom-0 top-[34%] z-20 bg-bottom bg-no-repeat 2xl:top-[36%]" style="background-image:linear-gradient(to bottom, #022c22 0%, transparent 14%), url('{{ asset('images/auth/parkflow-gate-hero.png') }}'); background-size:cover" role="img" aria-label="White vehicle entering a secure ParkFlow facility through an ANPR-enabled gate"></div>

        <div class="pf-plate absolute bottom-[17%] right-[5%] z-30 w-48 rounded-2xl border border-emerald-300/70 bg-[#053f31]/90 p-3 shadow-2xl backdrop-blur-md xl:right-[8%] xl:w-52 2xl:right-[10%] 2xl:w-56 2xl:p-4">
            <div class="mb-3 flex items-center gap-2 text-sm font-bold text-emerald-200"><span class="grid h-5 w-5 place-items-center rounded-full bg-emerald-400 text-[#022c22]"><x-icon name="check" class="h-3 w-3"/></span>Plate Recognized</div>
            <div class="rounded-lg bg-white px-3 py-2 text-center text-lg font-black tracking-wide text-[#102a23]">WP CAB 1234</div>
            <dl class="mt-3 grid grid-cols-[auto_1fr] gap-x-2 text-xs leading-5"><dt class="text-white/55">Vehicle:</dt><dd class="font-semibold">Car</dd><dt class="text-white/55">Entry:</dt><dd class="font-semibold">11:19 AM</dd><dt class="text-white/55">Status:</dt><dd class="font-bold text-emerald-300">Entry Verified</dd></dl>
        </div>

        <div class="pf-ticket absolute bottom-[12.5%] left-[22%] z-30 flex w-64 items-center gap-3 rounded-2xl border border-emerald-300 bg-white p-2.5 text-[#102a23] shadow-2xl xl:left-[24%] xl:w-72 2xl:left-[28%] 2xl:w-80 2xl:p-3">
            <svg viewBox="0 0 64 64" class="h-14 w-14 shrink-0 rounded-lg bg-emerald-50 p-1" aria-label="QR ticket"><path fill="#064e3b" d="M4 4h22v22H4zm5 5v12h12V9zm29-5h22v22H38zm5 5v12h12V9zM4 38h22v22H4zm5 5v12h12V43zm24-10h8v8h-8zm10 0h7v7h-7zm10 0h7v13h-7zM33 45h7v15h-7zm10-1h9v8h-9zm11 10h10v6H54z"/></svg>
            <span class="min-w-0"><span class="block text-sm font-black">Parking Ticket</span><span class="block text-xs text-slate-500">Scan for Check-Out</span></span>
            <span class="ml-auto grid h-10 w-10 place-items-center rounded-full bg-emerald-50 text-[#087f46]"><x-icon name="chevron-right" class="h-5 w-5"/></span>
        </div>

        <div class="pf-benefits absolute inset-x-6 bottom-3 z-30 grid grid-cols-5 rounded-3xl border border-emerald-400/30 bg-[#033d30]/90 px-3 py-2 shadow-2xl backdrop-blur-md xl:inset-x-8 2xl:inset-x-10 2xl:bottom-5 2xl:px-4 2xl:py-3">
            @foreach ([['car','Real-time','Occupancy'],['grid','Flexible','Packages'],['card','Secure','Payments'],['users','Multi-role','Access'],['shield','Audit &','Compliance']] as [$icon,$lineOne,$lineTwo])
                <div class="flex items-center justify-center gap-2 border-r border-white/15 px-2 last:border-r-0"><x-icon :name="$icon" class="h-5 w-5 shrink-0 text-emerald-300"/><span class="text-[10px] font-semibold leading-4 xl:text-xs">{{ $lineOne }}<br>{{ $lineTwo }}</span></div>
            @endforeach
        </div>
    </section>

    <main id="main" class="pf-main relative flex min-h-screen items-center justify-center overflow-hidden bg-[radial-gradient(circle_at_78%_15%,rgb(209_250_229/0.72),transparent_30%),#f8faf9] p-4 sm:p-8 lg:h-screen lg:min-h-0 lg:p-5 xl:p-7 2xl:p-14">
        <div class="pf-card-wrap relative w-full max-w-lg">
            <a href="{{ url('/') }}" class="mb-6 flex items-center justify-center gap-3 text-[#102a23] hover:text-[#102a23] lg:hidden">
                <span class="grid h-11 w-11 place-items-center rounded-xl bg-gradient-to-br from-emerald-400 to-[#087f46] text-2xl font-black text-white shadow-md">P</span><span><span class="block text-2xl font-black leading-none">ParkFlow</span><span class="text-[10px] uppercase tracking-widest text-slate-500">Smart Parking</span></span>
            </a>
            <div class="pf-card relative overflow-hidden rounded-[2rem] border border-white bg-white/95 p-6 shadow-[0_28px_80px_rgb(2_44_34/0.13)] sm:p-8 lg:p-7 2xl:p-11">
                <div class="pointer-events-none absolute -right-16 -top-16 h-48 w-48 rounded-full border-[22px] border-emerald-100/70"></div>
                <div class="pf-brand relative mb-5 hidden items-center gap-3 sm:flex 2xl:mb-9">
                    <span class="grid h-12 w-12 place-items-center rounded-xl bg-gradient-to-br from-emerald-400 to-[#087f46] text-2xl font-black text-white shadow-md">P</span><span><span class="block text-2xl font-black leading-none text-[#102a23]">ParkFlow<sup class="text-[9px]">®</sup></span><span class="text-xs text-slate-500">Smart Parking Management</span></span>
                </div>
                <div class="pf-content relative">
                    <h1 class="text-3xl font-black tracking-tight text-[#102a23] 2xl:text-4xl">{{ $title }}</h1>
                    @if ($subtitle)<p class="mt-1.5 max-w-sm text-sm leading-5 text-slate-500 2xl:mt-2 2xl:leading-6">{{ $subtitle }}</p>@endif
                    @if (session('status'))<div class="alert-success mt-4" role="status">{{ session('status') }}</div>@endif
                    <div class="pf-form-slot mt-5 2xl:mt-7">{{ $slot }}</div>
                </div>
                <div class="pf-skyline" aria-hidden="true">@foreach ([18,32,48,25,38,20,42,29,53,35,22,44,31,49,24] as $height)<span style="--building-height: {{ $height }}px"></span>@endforeach<x-icon name="car"/><b>P</b><x-icon name="car"/></div>
            </div>
            <p class="mt-3 text-center text-xs text-slate-500 2xl:mt-6">© {{ now()->year }} ParkFlow · Smart Parking Management</p>
        </div>
    </main>
</div>
</body>
</html>
