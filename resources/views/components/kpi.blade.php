@props(['label', 'value', 'hint' => null, 'href' => null])
{{-- KPI tile. `value` is an Alpine expression; a `hint` starting with ':' is an Alpine expression too. --}}
<div class="kpi">
    <span class="kpi-label">{{ $label }}</span>
    <span class="kpi-value" x-text="{{ $value }}">…</span>
    @if ($hint !== null)
        @if (str_starts_with($hint, ':'))<span class="text-xs text-ink-muted" x-text="{{ substr($hint, 1) }}"></span>
        @else<span class="text-xs text-ink-muted">{{ $hint }}</span>@endif
    @endif
    @if ($href)<a href="{{ $href }}" class="mt-1 text-xs font-semibold">View details →</a>@endif
</div>
