<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>ParkFlow · Smart Parking</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body class="min-h-screen bg-canvas">
<header class="bg-navy-deep text-white">
    <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4 sm:px-6">
        <span class="flex items-center gap-2.5"><span class="grid h-9 w-9 place-items-center rounded-lg bg-brand text-navy-deep font-black">P</span><span class="text-lg font-semibold">Park<span class="text-brand">Flow</span></span></span>
        <nav class="flex items-center gap-2" aria-label="Account">
            @auth<a href="{{ url('/dashboard') }}" class="btn bg-brand text-navy-deep hover:bg-brand-soft">Open dashboard</a>
            @else<a href="{{ route('login') }}" class="btn text-white hover:bg-white/10 hover:text-white">Sign in</a>
            @if (Route::has('register'))<a href="{{ route('register') }}" class="btn bg-brand text-navy-deep hover:bg-brand-soft hidden sm:inline-flex">Create customer account</a>@endif @endauth
        </nav>
    </div>
    <div class="mx-auto max-w-6xl px-4 pb-14 pt-8 sm:px-6">
        <h1 class="max-w-2xl text-3xl font-semibold text-white sm:text-4xl">Park, track and pay — with receipts you can check any time.</h1>
        <p class="mt-4 max-w-2xl text-white/80">ParkFlow runs the gates of this car park: number-plate Check-In and Check-Out verified by staff, daily, weekly and monthly packages, and bills in Sri Lankan rupees.</p>
    </div>
</header>
<main id="main" class="mx-auto grid max-w-6xl gap-4 px-4 py-10 sm:grid-cols-3 sm:px-6">
    <section class="card card-pad"><h2 class="card-title">For customers</h2><p class="mt-2 text-sm text-ink-muted">Register your vehicles, see your active parking with remaining time, download receipts and contact the parking office.</p></section>
    <section class="card card-pad"><h2 class="card-title">At the gate</h2><p class="mt-2 text-sm text-ink-muted">Staff record your Check-In from the number plate. You do not need to scan anything; keep the printed ticket in case it is requested.</p></section>
    <section class="card card-pad"><h2 class="card-title">Fair, transparent billing</h2><p class="mt-2 text-sm text-ink-muted">Every charge is calculated by the server from published rates, and every receipt lists the breakdown in LKR.</p></section>
</main>
<footer class="mx-auto max-w-6xl px-4 pb-10 text-xs text-ink-muted sm:px-6">Times are shown in Sri Lanka time (Asia/Colombo). Plate images are kept only for the period set by the parking office and are never public.</footer>
</body></html>
