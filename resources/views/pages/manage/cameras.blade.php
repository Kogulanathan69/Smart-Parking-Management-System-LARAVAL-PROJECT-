<x-layouts.app title="Cameras" wide>
<div x-data="{ token: null }"><div x-data="crudPage({ url: '/admin/anpr/cameras', blank: { code: '', name: '', direction: 'entry', camera_type: 'ip_rtsp', gate_code: '', stream_url: '', stream_username: '', stream_password: '' },
        create: (d) => { const b = { ...d }; ['gate_code','stream_url','stream_username','stream_password'].forEach(k => { if (!b[k]) delete b[k]; }); return ParkFlow.api.post('/admin/anpr/cameras', b); },
        after(res) { this.token = { code: res.data.data.code, value: res.data.device_token }; } })">
    <x-page-header title="ANPR cameras" subtitle="Each camera agent authenticates with its own device token. Stream credentials are stored encrypted and are never shown again.">
        <button type="button" class="btn-primary" x-show="raw?.meta?.configuration_state !== 'not_configured'" @click="openCreate()"><x-icon name="plus" class="h-4 w-4"/>Register camera</button>
    </x-page-header>
    <div class="alert-info mb-4">No camera hardware has been tested with this installation yet. Cameras show <em>Online</em> only when their agent sends heartbeats.</div>
    <div x-show="raw?.meta?.configuration_state === 'not_configured'" class="alert-warning mb-4"><strong>Camera module not configured.</strong> The optional ANPR database schema is not installed. Device registration is unavailable until the module is installed.</div>
    @include('pages.manage._token-once')
    <div class="summary-strip" x-show="loaded"><div class="kpi"><span class="kpi-value" x-text="rows.length"></span><span class="kpi-label">Registered devices</span></div><div class="kpi"><span class="kpi-value text-success-ink" x-text="rows.filter(c => c.is_enabled && c.status === 'online').length"></span><span class="kpi-label">Online cameras</span></div><div class="kpi"><span class="kpi-value" x-text="rows.filter(c => c.is_enabled).length"></span><span class="kpi-label">Enabled cameras</span></div></div>
    <div class="card"><div class="table-wrap"><table class="tbl"><thead><tr><th>Camera</th><th>Direction</th><th>Type</th><th>Stream host</th><th>Status</th><th>Last heartbeat</th><th>Last reading</th></tr></thead><tbody>
        <x-list-states cols="7" empty="No cameras registered."/>
        <template x-for="c in rows" :key="c.id"><tr><td><a :href="`/manage/cameras/${c.id}`" class="font-semibold" x-text="c.code"></a><span class="block text-xs text-ink-muted" x-text="c.name"></span></td>
            <td x-text="$fmt.titleCase(c.direction)"></td><td x-text="$fmt.titleCase(c.camera_type)"></td><td class="font-mono text-xs" x-text="c.stream_host || '—'"></td>
            <td><x-state kind="camera" value="c.is_enabled ? c.status : 'disabled'"/></td><td x-text="$fmt.dateTime(c.last_heartbeat_at, 'Never')"></td><td x-text="$fmt.dateTime(c.last_capture_at, 'Never')"></td></tr></template>
    </tbody></table></div></div>
    <x-modal show="modal" title="Register camera" size="max-w-2xl">
        <form id="cm-f" class="grid gap-3 sm:grid-cols-2" @submit.prevent="save()">
            <x-xfield label="Code" model="f.code" name="code" form="$data" required hint="e.g. CAM-IN-2. Cannot be changed later."/>
            <x-xfield label="Name" model="f.name" name="name" form="$data" required/>
            <div><label class="label" for="cm-d">Direction</label><select id="cm-d" class="input" x-model="f.direction"><option value="entry">Entry (Check-In)</option><option value="exit">Exit (Check-Out)</option><option value="dual">Both</option></select></div>
            <div><label class="label" for="cm-t">Type</label><select id="cm-t" class="input" x-model="f.camera_type"><option value="ip_rtsp">IP camera (RTSP)</option><option value="ip_http_snapshot">IP camera (HTTP snapshot)</option><option value="usb_local">USB camera on the agent PC</option><option value="other">Other</option></select></div>
            <x-xfield label="Gate code (optional)" model="f.gate_code" name="gate_code" form="$data"/>
            <div class="sm:col-span-2" x-show="f.camera_type !== 'usb_local'"><label class="label" for="cm-u">Stream URL (no username or password in it)</label><input id="cm-u" class="input font-mono" x-model="f.stream_url" placeholder="rtsp://192.168.1.20:554/stream1" autocomplete="off">
                <p class="hint">Must point to an address on the camera network allowed by the server.</p><p class="field-error" x-show="err('stream_url')" x-text="err('stream_url')"></p></div>
            <div x-show="f.camera_type !== 'usb_local'"><label class="label" for="cm-un">Stream username</label><input id="cm-un" class="input" x-model="f.stream_username" autocomplete="off"></div>
            <div x-show="f.camera_type !== 'usb_local'"><label class="label" for="cm-pw">Stream password</label><input id="cm-pw" type="password" class="input" x-model="f.stream_password" autocomplete="new-password"></div>
        </form>
        <x-slot:footer><button type="button" class="btn-secondary" @click="modal = false">Cancel</button><button form="cm-f" class="btn-primary" :disabled="busy">Register</button></x-slot:footer>
    </x-modal>
</div></div>
</x-layouts.app>
