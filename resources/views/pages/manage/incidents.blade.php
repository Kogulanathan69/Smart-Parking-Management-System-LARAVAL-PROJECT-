<x-layouts.app title="Incidents" wide>
<div x-data="crudPage({ url: '/admin/incidents', filters: { status: '' },
        toForm: (r) => ({ status: r.status, severity: r.severity, resolution: r.resolution || '', compensation: ParkFlow.fmt.minorToRupeesInput(r.compensation_minor) }),
        update: (r, d) => ParkFlow.api.patch(`/admin/incidents/${r.id}`, { status: d.status, severity: d.severity, resolution: d.resolution || null, compensation_minor: d.compensation === '' ? null : ParkFlow.fmt.rupeesToMinor(d.compensation) }) })">
    <x-page-header title="Incidents" subtitle="Damage, theft and accident reports from staff.">
        <a href="{{ route('ops.incidents.create') }}" class="btn-secondary">Report incident</a>
        <div><label class="sr-only" for="in-s">Status</label><select id="in-s" class="input" x-model="filters.status" @change="search()"><option value="">All</option><option value="open">Open</option><option value="investigating">Investigating</option><option value="resolved">Resolved</option><option value="closed">Closed</option></select></div>
    </x-page-header>
    <div class="card"><div class="table-wrap"><table class="tbl"><thead><tr><th>Reference</th><th>Type</th><th>Severity</th><th>Description</th><th>Status</th><th class="tbl-num">Compensation</th><th>Reported</th><th></th></tr></thead><tbody>
        <x-list-states cols="8" empty="No incidents."/>
        <template x-for="i in rows" :key="i.id"><tr><td class="font-mono text-xs" x-text="i.reference"></td><td x-text="$fmt.titleCase(i.type)"></td><td><x-state kind="severity" value="i.severity"/></td>
            <td class="max-w-sm text-sm" x-text="i.description"></td><td><x-state kind="incident" value="i.status"/></td><td class="tbl-num" x-text="$fmt.money(i.compensation_minor)"></td><td x-text="$fmt.dateTime(i.created_at)"></td>
            <td class="text-right"><button type="button" class="btn-ghost btn-sm" @click="openEdit(i)">Update</button></td></tr></template>
    </tbody></table></div><x-pager/></div>
    <x-modal show="modal" title=":`Incident ${current?.reference ?? ''}`">
        <form id="ie-f" class="grid gap-3 sm:grid-cols-2" @submit.prevent="save()">
            <div><label class="label" for="ie-s">Status</label><select id="ie-s" class="input" x-model="f.status"><option value="open">Open</option><option value="investigating">Investigating</option><option value="resolved">Resolved</option><option value="closed">Closed</option></select></div>
            <div><label class="label" for="ie-v">Severity</label><select id="ie-v" class="input" x-model="f.severity"><option value="low">Low</option><option value="medium">Medium</option><option value="high">High</option></select></div>
            <div class="sm:col-span-2"><label class="label" for="ie-r">Resolution</label><textarea id="ie-r" class="input" x-model="f.resolution" maxlength="5000"></textarea></div>
            <div><label class="label" for="ie-c">Compensation (LKR)</label><input id="ie-c" class="input" inputmode="decimal" x-model="f.compensation"><p class="hint">Recorded only — paying it is handled outside ParkFlow.</p></div>
            <template x-if="(current?.evidence || []).length"><div class="sm:col-span-2"><p class="label">Evidence</p><div class="flex flex-wrap gap-2"><template x-for="e in current.evidence" :key="e.uuid"><a :href="e.url" target="_blank" rel="noopener" class="btn-secondary btn-sm" x-text="e.original_name"></a></template></div></div></template>
        </form>
        <x-slot:footer><button type="button" class="btn-secondary" @click="modal = false">Cancel</button><button form="ie-f" class="btn-primary" :disabled="busy">Save</button></x-slot:footer>
    </x-modal>
</div>
</x-layouts.app>
