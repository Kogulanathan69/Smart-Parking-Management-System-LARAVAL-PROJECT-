@props(['title' => 'Sign in', 'subtitle' => null])
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · ParkFlow</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-canvas">
<div class="grid min-h-screen lg:grid-cols-[minmax(0,5fr)_minmax(0,7fr)]">
    <section class="relative hidden overflow-hidden bg-navy-deep p-10 text-white lg:flex lg:flex-col lg:justify-between" aria-hidden="true">
        <a href="{{ url('/') }}" class="flex items-center gap-2.5 text-white hover:text-white">
            <span class="grid h-10 w-10 place-items-center rounded-lg bg-brand text-navy-deep text-xl font-black">P</span>
            <span class="text-xl font-semibold">Park<span class="text-brand">Flow</span></span>
        </a>
        <div class="max-w-md">
            <p class="text-3xl font-semibold leading-snug">Smart parking operations for every gate, zone and shift.</p>
            <p class="mt-4 text-white/75">Check-In and Check-Out with plate recognition that staff always verify, server-calculated bills in LKR, and a full audit trail.</p>
        </div>
        <p class="text-xs text-white/70">Times shown in Asia/Colombo (Sri Lanka).</p>
        <div class="pointer-events-none absolute -right-24 -top-24 h-80 w-80 rounded-full border-[40px] border-brand/15"></div>
    </section>
    <main id="main" class="flex items-center justify-center p-4 sm:p-8">
        <div class="w-full max-w-md">
            <a href="{{ url('/') }}" class="mb-6 flex items-center gap-2 text-ink hover:text-ink lg:hidden">
                <span class="grid h-9 w-9 place-items-center rounded-lg bg-navy text-brand font-black">P</span><span class="text-lg font-semibold">Park<span class="text-brand-strong">Flow</span></span>
            </a>
            <div class="card p-6 sm:p-8">
                <h1 class="text-2xl font-semibold">{{ $title }}</h1>
                @if ($subtitle)<p class="mt-1 text-sm text-ink-muted">{{ $subtitle }}</p>@endif
                @if (session('status'))<div class="alert-success mt-4" role="status">{{ session('status') }}</div>@endif
                <div class="mt-6">{{ $slot }}</div>
            </div>
        </div>
    </main>
</div>
</body>
</html>
