@props(['empty' => 'Nothing to show yet.', 'cols' => 1])
{{-- Loading / error / empty rows for a dataTable() scope. Place inside <tbody>. --}}
<template x-if="loading && !rows.length"><tr><td colspan="{{ $cols }}" class="py-6"><div class="space-y-2"><div class="skeleton h-4 w-2/3"></div><div class="skeleton h-4 w-1/2"></div><div class="skeleton h-4 w-3/5"></div></div><span class="sr-only">Loading…</span></td></tr></template>
<template x-if="error"><tr><td colspan="{{ $cols }}" class="py-6"><div class="alert-danger flex flex-wrap items-center justify-between gap-2" role="alert"><span x-text="error"></span><button type="button" class="btn-secondary btn-sm" @click="load()">Try again</button></div></td></tr></template>
<template x-if="empty"><tr><td colspan="{{ $cols }}" class="py-10 text-center text-sm text-ink-muted">{{ $empty }}</td></tr></template>
