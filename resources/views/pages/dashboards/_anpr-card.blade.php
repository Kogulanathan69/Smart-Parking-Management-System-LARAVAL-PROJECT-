<section class="card card-pad" aria-labelledby="anpr-h" x-data="anprSnapshot()">
    <div class="flex items-center justify-between"><h2 id="anpr-h" class="card-title">Cameras & recognition</h2><a href="{{ route('reports.anpr') }}" class="text-xs font-semibold">Details</a></div>
    <dl class="dl mt-3">
        <dt>Recognition engine</dt><dd><span class="chip" :class="engine === null ? 'chip-neutral' : (engine.available ? 'chip-success' : 'chip-warning')" x-text="engine === null ? 'Checking…' : (({ not_configured: 'Not configured', starting: 'Starting', online: 'Online', offline: 'Offline', unreachable: 'Unreachable', recognition_error: 'Recognition error' })[engine.state] || 'Unknown') + ' (' + engine.driver + ')' "></span></dd>
        <dt>Review required</dt><dd><a href="{{ route('ops.anpr.queue') }}" x-text="pending ?? '…'"></a></dd>
    </dl>
    <ul class="mt-3 divide-y divide-line text-sm">
        <template x-for="c in cameras" :key="c.id"><li class="flex items-center justify-between gap-2 py-2"><span><span class="font-semibold" x-text="c.code"></span> <span class="text-ink-muted" x-text="c.name"></span></span><x-state kind="camera" value="c.is_enabled ? c.status : 'disabled'"/></li></template>
        <li x-show="cameras && !cameras.length" class="py-2 text-ink-muted">No cameras registered.</li>
    </ul>
    <p x-show="configured === false" class="mt-3 rounded-lg bg-neutral p-3 text-xs text-neutral-ink">ANPR storage and camera hardware are not configured. Recognition workflows require the optional ANPR schema.</p>
</section>
