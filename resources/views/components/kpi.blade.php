@props(['label', 'value', 'hint' => null, 'href' => null, 'icon' => null])
{{-- KPI tile. `value` is an Alpine expression; a `hint` starting with ':' is an Alpine expression too. --}}
<div class="kpi">
    <div class="flex items-center gap-3">
        @if($icon)<span class="grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-brand-faint text-brand-strong"><x-icon :name="$icon" class="h-6 w-6"/></span>@endif
        <span class="min-w-0"><span class="kpi-value block" x-text="{{ $value }}">…</span><span class="kpi-label block">{{ $label }}</span></span>
    </div>
    @if ($hint !== null)
        @if (str_starts_with($hint, ':'))<span class="text-xs text-ink-muted" x-text="{{ substr($hint, 1) }}"></span>
        @else<span class="text-xs text-ink-muted">{{ $hint }}</span>@endif
    @endif
    @if ($href)<a href="{{ $href }}" class="mt-2 text-xs font-semibold">View details →</a>@endif
</div>
