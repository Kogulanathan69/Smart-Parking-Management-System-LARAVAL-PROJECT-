<x-layouts.app title="Camera" :crumbs="['Cameras' => route('manage.cameras')]">
<div x-data="cameraDetail({{ (int) $id }})">
    @include('pages.ops._record-states', ['var' => 'c'])
    <template x-if="c"><div>
        <x-page-header title="Camera">
            <button type="button" class="btn-secondary" @click="startEdit()">Edit</button>
            <button type="button" :class="c.is_enabled ? 'btn-secondary' : 'btn-primary'" @click="toggle()" :disabled="busy" x-text="c.is_enabled ? 'Disable' : 'Enable'"></button>
            <button type="button" class="btn-danger" @click="rotate()" :disabled="busy">Rotate token</button>
        </x-page-header>
        <div class="alert-info mb-4"><strong>Device security.</strong> Stream credentials remain encrypted and device tokens are shown only when rotated.</div>
        <template x-if="token"><div class="alert-warning mb-4" role="alert"><p class="font-semibold">New device token — shown only once</p>
            <div class="mt-2 flex flex-col gap-2 sm:flex-row"><input class="input font-mono text-xs" :value="token" readonly @focus="$event.target.select()" aria-label="New device token"><button type="button" class="btn-secondary" @click="navigator.clipboard?.writeText(token).then(() => ParkFlow.toast('Copied.'))">Copy</button><button type="button" class="btn-primary" @click="token = null">I have stored it</button></div></div></template>
        <div class="grid gap-4 lg:grid-cols-2">
            <section class="card card-pad"><div class="flex items-center justify-between"><h2 class="text-lg font-semibold" x-text="`${c.code} · ${c.name}`"></h2><x-state kind="camera" value="c.is_enabled ? c.status : 'disabled'"/></div>
                <dl class="dl mt-3"><dt>Direction</dt><dd x-text="$fmt.titleCase(c.direction)"></dd><dt>Type</dt><dd x-text="$fmt.titleCase(c.camera_type)"></dd><dt>Gate</dt><dd x-text="c.gate_code || '—'"></dd>
                    <dt>Stream host</dt><dd class="font-mono" x-text="c.stream_host || 'Not set'"></dd><dt>Credentials</dt><dd x-text="c.credentials_configured ? 'Stored (encrypted)' : 'None'"></dd>
                    <dt>Token rotated</dt><dd x-text="$fmt.dateTime(c.token_rotated_at)"></dd><dt>Last heartbeat</dt><dd x-text="$fmt.dateTime(c.last_heartbeat_at, 'Never')"></dd>
                    <dt>Last reading</dt><dd x-text="$fmt.dateTime(c.last_capture_at, 'Never')"></dd><dt>Last error</dt><dd x-text="c.last_error ? `${c.last_error} (${$fmt.dateTime(c.last_error_at)})` : '—'"></dd></dl></section>
            <section class="card card-pad"><h2 class="card-title">Last 24 hours</h2>
                <ul class="mt-3 space-y-1 text-sm"><template x-for="[k, v] in Object.entries(health?.events_last_24h || {})" :key="k"><li class="flex justify-between"><span x-text="$fmt.stateLabel('recognition', k)"></span><span class="font-semibold" x-text="v"></span></li></template>
                    <li x-show="!Object.keys(health?.events_last_24h || {}).length" class="text-ink-muted">No readings.</li></ul>
                <h3 class="section-title mt-5">Health log (state changes)</h3>
                <ul class="mt-2 divide-y divide-line text-sm"><template x-for="(l, i) in health?.recent_logs || []" :key="i"><li class="py-2"><span class="font-semibold" x-text="$fmt.titleCase(l.status)"></span> <span class="text-ink-muted" x-text="$fmt.dateTime(l.created_at)"></span><p class="text-xs text-ink-muted" x-text="l.message || ''"></p></li></template>
                    <li x-show="!(health?.recent_logs || []).length" class="py-2 text-ink-muted">No changes recorded.</li></ul></section>
        </div>
        <x-modal show="edit" title="Edit camera">
            <form id="ce-f" class="grid gap-3 sm:grid-cols-2" @submit.prevent="save()">
                <div class="sm:col-span-2"><label class="label" for="ce-n">Name</label><input id="ce-n" class="input" x-model="f.name" maxlength="100"></div>
                <div><label class="label" for="ce-d">Direction</label><select id="ce-d" class="input" x-model="f.direction"><option value="entry">Entry</option><option value="exit">Exit</option><option value="dual">Both</option></select></div>
                <div><label class="label" for="ce-t">Type</label><select id="ce-t" class="input" x-model="f.camera_type"><option value="ip_rtsp">IP (RTSP)</option><option value="ip_http_snapshot">IP (HTTP snapshot)</option><option value="usb_local">USB</option><option value="other">Other</option></select></div>
                <div><label class="label" for="ce-g">Gate code</label><input id="ce-g" class="input" x-model="f.gate_code" maxlength="40"></div>
                <div class="sm:col-span-2"><label class="label" for="ce-u">New stream URL</label><input id="ce-u" class="input font-mono" x-model="f.stream_url" placeholder="Leave empty to keep the current one" autocomplete="off"><p class="field-error" x-show="errors.stream_url" x-text="errors.stream_url?.[0]"></p></div>
                <div><label class="label" for="ce-un">New username</label><input id="ce-un" class="input" x-model="f.stream_username" placeholder="Keep current" autocomplete="off"></div>
                <div><label class="label" for="ce-pw">New password</label><input id="ce-pw" type="password" class="input" x-model="f.stream_password" placeholder="Keep current" autocomplete="new-password"></div></form>
            <x-slot:footer><button type="button" class="btn-secondary" @click="edit = false">Cancel</button><button form="ce-f" class="btn-primary" :disabled="busy">Save</button></x-slot:footer>
        </x-modal>
    </div></template>
</div>
</x-layouts.app>
