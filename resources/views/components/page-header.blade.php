@props(['title', 'subtitle' => null])
<div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
    <div class="min-w-0"><h1 class="page-title">{{ $title }}</h1>@if($subtitle)<p class="mt-1 text-sm text-ink-muted">{{ $subtitle }}</p>@endif</div>
    @if (trim($slot))<div class="flex flex-wrap gap-2">{{ $slot }}</div>@endif
</div>
