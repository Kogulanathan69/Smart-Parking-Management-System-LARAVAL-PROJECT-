{{-- Customer session card; expects `s` (ParkingSession resource). All times/amounts are server values. --}}
<article class="card card-pad">
    <div class="flex items-start justify-between gap-2">
        <div><span class="plate" x-text="s.vehicle?.plate_number || s.vehicle?.identifier"></span>
            <p class="mt-1 text-xs text-ink-muted" x-text="`${s.vehicle_category} · ${s.package?.name ?? ''}`"></p></div>
        <x-state kind="session" value="s.status"/>
    </div>
    <dl class="dl mt-3">
        <dt>Checked in</dt><dd x-text="$fmt.dateTime(s.entry_at)"></dd>
        <dt>Zone / slot</dt><dd x-text="`${s.zone?.code ?? '—'} / ${s.slot?.code ?? '—'}`"></dd>
        <dt>Package ends</dt><dd x-text="s.package_ends_at ? $fmt.dateTime(s.package_ends_at) : 'No fixed end (hourly)'"></dd>
        <dt>Time</dt><dd><x-state kind="time" value="s.time?.status"/>
            <span class="ml-1 text-xs text-ink-muted" x-show="s.time?.remaining_minutes" x-text="`${$fmt.minutes(s.time?.remaining_minutes)} left`"></span>
            <span class="ml-1 text-xs text-danger-ink" x-show="s.time?.overdue_minutes" x-text="`${$fmt.minutes(s.time?.overdue_minutes)} over`"></span></dd>
    </dl>
    <a :href="`/my/history/${s.uuid}`" class="btn-secondary btn-sm mt-3">View bill so far</a>
</article>
