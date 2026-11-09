<x-layouts.app title="Audit findings" wide>
<div x-data="crudPage({ url: '/audit/findings', blank: { title: '', body: '', severity: 'medium', subject_type: '', subject_id: '' },
        create: (d) => ParkFlow.api.post('/audit/findings', { ...d, subject_type: d.subject_type || null, subject_id: d.subject_type ? Number(d.subject_id) : null }) })">
    <x-page-header title="Audit findings" subtitle="Observations recorded by auditors. Recording a finding never changes parking, payment or gate records.">
        <button type="button" class="btn-primary" x-show="$store.app.can('auditor')" @click="openCreate()"><x-icon name="plus" class="h-4 w-4"/>Record finding</button>
    </x-page-header>
    <div class="alert-info mb-4"><strong>Audit note boundary.</strong> Findings document observations only and never mutate operational records.</div>
    <div class="card"><div class="table-wrap"><table class="tbl"><thead><tr><th>When</th><th>Severity</th><th>Finding</th><th>Subject</th></tr></thead><tbody>
        <x-list-states cols="4" empty="No findings recorded."/>
        <template x-for="f2 in rows" :key="f2.id"><tr><td class="whitespace-nowrap" x-text="$fmt.dateTime(f2.created_at)"></td><td><x-state kind="severity" value="f2.severity"/></td>
            <td><p class="font-semibold" x-text="f2.title"></p><p class="whitespace-pre-line text-sm text-ink-muted" x-text="f2.body"></p></td><td x-text="f2.subject_type ? `${$fmt.titleCase(f2.subject_type)} #${f2.subject_id}` : '—'"></td></tr></template>
    </tbody></table></div><x-pager/></div>
    <x-modal show="modal" title="Record audit finding" size="max-w-2xl">
        <form id="af-f" class="grid gap-3 sm:grid-cols-2" @submit.prevent="save()">
            <x-xfield class="sm:col-span-2" label="Title" model="f.title" name="title" form="$data" required/>
            <x-xfield class="sm:col-span-2" label="Finding" model="f.body" name="body" type="textarea" form="$data" required/>
            <div><label class="label" for="af-s">Severity</label><select id="af-s" class="input" x-model="f.severity"><option value="info">Info</option><option value="low">Low</option><option value="medium">Medium</option><option value="high">High</option></select></div>
            <div><label class="label" for="af-t">Related record (optional)</label><select id="af-t" class="input" x-model="f.subject_type"><option value="">None</option><option value="session">Session</option><option value="payment">Payment</option><option value="user">User</option><option value="inspection">Inspection</option><option value="incident">Incident</option></select></div>
            <div x-show="f.subject_type"><label class="label" for="af-i">Record ID</label><input id="af-i" class="input" inputmode="numeric" x-model="f.subject_id"><p class="field-error" x-show="err('subject_id')" x-text="err('subject_id')"></p></div>
        </form>
        <x-slot:footer><button type="button" class="btn-secondary" @click="modal = false">Cancel</button><button form="af-f" class="btn-primary" :disabled="busy">Record finding</button></x-slot:footer>
    </x-modal>
</div>
</x-layouts.app>
