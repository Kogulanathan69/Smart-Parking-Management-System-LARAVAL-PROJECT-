<x-layouts.app title="System status">
<div x-data="systemStatus()">
    <x-page-header title="System status" subtitle="Which integrations are real and which are simulated. Read-only; drivers are set in the server configuration."/>
    <template x-if="error"><div class="alert-danger" x-text="error"></div></template>
    <template x-if="!d && !error"><div class="card card-pad"><div class="skeleton h-24"></div></div></template>
    <template x-if="d"><div class="grid gap-4 lg:grid-cols-2">
        <section class="detail-hero"><div class="flex items-center gap-3"><span class="filter-heading-icon"><x-icon name="pulse"/></span><h2 class="card-title">Integrations</h2></div>
            <div class="table-wrap mt-3"><table class="tbl"><thead><tr><th>Integration</th><th>Driver</th><th>Mode</th></tr></thead><tbody>
                <template x-for="[k, v] in Object.entries(d.drivers)" :key="k"><tr><td x-text="({ gate_controller: 'Gate / barrier', payment_gateway: 'Payment gateway', ticket_printer: 'Ticket printer', plate_recognizer: 'Legacy plate input', anpr_engine: 'Plate recognition engine' })[k] || k"></td>
                    <td class="font-mono text-xs" x-text="v.driver"></td><td><span :class="v.simulated ? 'chip-info' : 'chip-success'" x-text="v.simulated ? 'Simulated / manual' : 'Connected integration'"></span></td></tr></template>
            </tbody></table></div>
            <p class="mt-3 text-xs text-ink-muted">"Simulated" means no hardware or external service is driven: e.g. gate commands are logged only and staff open the barrier by hand.</p></section>
        <section class="detail-hero"><div class="flex items-center gap-3"><span class="filter-heading-icon"><x-icon name="camera"/></span><h2 class="card-title">Plate recognition & cameras</h2></div>
            <dl class="dl mt-3"><dt>Engine</dt><dd><span :class="d.anpr_engine.available ? 'chip-success' : 'chip-danger'" x-text="(({ not_configured: 'Not configured', starting: 'Starting', online: 'Online', offline: 'Offline', unreachable: 'Unreachable', recognition_error: 'Recognition error' })[d.anpr_engine.state] || 'Unknown') + (d.anpr_engine.latency_ms !== null ? ' (' + d.anpr_engine.latency_ms + ' ms)' : '')"></span></dd>
                <dt>Verification</dt><dd x-text="d.verification_mode === 'always' ? 'Staff verify every reading' : 'Only uncertain readings need explicit verification'"></dd>
                <dt>Configuration</dt><dd><span :class="d.cameras.configured ? 'chip-success' : 'chip-neutral'" x-text="d.cameras.configured ? 'Configured' : 'Not configured'"></span></dd>
                <dt>Cameras</dt><dd x-text="d.cameras.configured ? `${d.cameras.online} online of ${d.cameras.enabled} enabled (${d.cameras.total} registered)` : 'No camera schema or devices installed'"></dd>
                <dt>Recognition workflow</dt><dd><span :class="d.cameras.configured ? 'chip-success' : 'chip-neutral'" x-text="d.cameras.configured ? 'Available' : 'Not configured'"></span></dd></dl>
            <a href="{{ route('manage.cameras') }}" class="mt-3 inline-block text-sm font-semibold">Manage cameras →</a></section>
        <section class="card card-pad"><div class="flex items-center gap-3"><span class="filter-heading-icon"><x-icon name="gate"/></span><h2 class="card-title">Gate</h2></div>
            <dl class="dl mt-3"><dt>Slot released on</dt><dd x-text="d.gate.release_slot_on === 'gate' ? 'Staff confirmation of departure' : 'Settlement'"></dd>
                <dt>Last command</dt><dd x-text="d.gate.last_command ? `${$fmt.titleCase(d.gate.last_command.status)} · ${d.gate.last_command.driver} · ${$fmt.dateTime(d.gate.last_command.at)}` : 'None yet'"></dd>
                <dt>Failures (24 h)</dt><dd x-text="d.gate.failed_last_24h"></dd></dl></section>
        <section class="card card-pad"><div class="flex items-center gap-3"><span class="filter-heading-icon"><x-icon name="cog"/></span><h2 class="card-title">Environment</h2></div>
            <dl class="dl mt-3"><dt>Environment</dt><dd x-text="d.environment"></dd><dt>Time zone</dt><dd x-text="d.timezone"></dd>
                <dt>Backups</dt><dd class="font-normal text-ink-muted">Not monitored by ParkFlow. Follow the backup procedure in the runbook.</dd></dl></section>
    </div></template>
</div>
</x-layouts.app>
