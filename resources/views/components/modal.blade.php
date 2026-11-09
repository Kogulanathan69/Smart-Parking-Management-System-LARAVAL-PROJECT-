@props(['show', 'title', 'size' => 'max-w-lg', 'close' => null, 'panelClass' => ''])
{{-- Accessible dialog bound to an Alpine boolean. Focus is trapped while open; Escape closes. --}}
@php $close = $close ?? ($show.' = false'); $id = 'dlg-'.\Illuminate\Support\Str::random(6); @endphp
<template x-teleport="body">
<div x-show="{{ $show }}" x-cloak class="fixed inset-0 z-50 flex items-end justify-center bg-navy-deep/50 p-0 sm:items-center sm:p-4" @keydown.escape.window="{{ $show }} && ({{ $close }})">
    <div class="card {{ $panelClass }} max-h-[92vh] w-full {{ $size }} overflow-y-auto rounded-b-none sm:rounded-b-[var(--radius-card)]" role="dialog" aria-modal="true" aria-labelledby="{{ $id }}" x-trap.noscroll.inert="{{ $show }}" @click.outside="{{ $close }}">
        <div class="sticky top-0 z-10 flex items-center justify-between gap-3 border-b border-line bg-gradient-to-r from-brand-faint to-surface px-5 py-4">
            <h2 id="{{ $id }}" class="text-lg font-semibold" @if(str_starts_with($title, ':')) x-text="{{ substr($title, 1) }}" @endif>{{ str_starts_with($title, ':') ? '' : $title }}</h2>
            <button type="button" class="rounded-md p-1.5 text-ink-muted hover:bg-canvas" @click="{{ $close }}" aria-label="Close"><x-icon name="x"/></button>
        </div>
        <div class="px-5 py-4">{{ $slot }}</div>
        @isset($footer)<div class="sticky bottom-0 flex flex-col-reverse gap-2 border-t border-line bg-surface px-5 py-3 sm:flex-row sm:justify-end">{{ $footer }}</div>@endisset
    </div>
</div>
</template>

