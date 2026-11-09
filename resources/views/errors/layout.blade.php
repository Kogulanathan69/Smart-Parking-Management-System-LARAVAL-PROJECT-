<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>@yield('title') · ParkFlow</title>
@php try { $vite = app(\Illuminate\Foundation\Vite::class)(['resources/css/app.css']); } catch (\Throwable $e) { $vite = null; } @endphp
{!! $vite !!}</head>
<body class="grid min-h-screen place-items-center bg-canvas p-4">
<main class="card w-full max-w-lg p-8 text-center">
    <p class="font-mono text-sm font-semibold text-brand-strong">@yield('code')</p>
    <h1 class="mt-2 text-2xl font-semibold">@yield('title')</h1>
    <p class="mt-3 text-ink-muted">@yield('message')</p>
    <div class="mt-6 flex flex-col justify-center gap-2 sm:flex-row">
        @yield('actions')
        @auth<a href="{{ url('/dashboard') }}" class="btn-primary">Go to my dashboard</a>@else<a href="{{ url('/login') }}" class="btn-primary">Sign in</a>@endauth
    </div>
</main>
</body></html>
