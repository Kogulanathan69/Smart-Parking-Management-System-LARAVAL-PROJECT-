<x-layouts.app title="Report incident">
<div x-data="incidentForm()">
    <x-page-header title="Report an incident" subtitle="Damage, theft, accidents or anything that needs follow-up. Admins are notified."/>
    <template x-if="done"><div class="alert-success mb-4" role="status">Incident <strong x-text="done.reference"></strong> reported. <a href="{{ route('ops.incidents.create') }}">Report another</a></div></template>
    <form class="card card-pad grid max-w-3xl gap-4 border-warning-line sm:grid-cols-2" @submit.prevent="submit()" x-show="!done">
        <div class="flex items-center gap-3 border-b border-line pb-4 sm:col-span-2"><span class="grid h-11 w-11 place-items-center rounded-xl bg-warning text-warning-ink"><x-icon name="alert"/></span><div><h2 class="card-title">Incident details</h2><p class="text-sm text-ink-muted">Describe only what was observed. Evidence remains private.</p></div></div>
        <div><label class="label" for="ic-t">Type</label><select id="ic-t" class="input" x-model="data.type"><option value="damage">Damage</option><option value="theft">Theft</option><option value="accident">Accident</option><option value="other">Other</option></select></div>
        <div><label class="label" for="ic-s">Severity</label><select id="ic-s" class="input" x-model="data.severity"><option value="low">Low</option><option value="medium">Medium</option><option value="high">High</option></select></div>
        <div class="sm:col-span-2"><label class="label" for="ic-sess">Session ID (optional)</label><input id="ic-sess" class="input font-mono text-xs" x-model="data.parking_session_uuid" placeholder="Filled in when opened from a session">
            <p class="field-error" x-show="errors.parking_session_uuid" x-text="errors.parking_session_uuid?.[0]"></p></div>
        <div class="sm:col-span-2"><label class="label" for="ic-d">What happened? <span class="text-danger-ink">*</span></label><textarea id="ic-d" class="input min-h-32" x-model="data.description" maxlength="5000" required></textarea>
            <p class="field-error" x-show="errors.description" x-text="errors.description?.[0]"></p></div>
        <div class="sm:col-span-2"><label class="label" for="ic-p">Photo (optional)</label><input id="ic-p" type="file" class="input" accept=".jpg,.jpeg,.png,.webp,image/*" capture="environment" @change="file = $event.target.files[0] || null"><p class="hint">Stored privately; only authorised staff can view it.</p></div>
        <div class="sm:col-span-2"><button class="btn-primary" :disabled="busy">Report incident</button></div>
    </form>
</div>
</x-layouts.app>
