<x-layouts.app title="Customer support" wide>
<div x-data="crudPage({ url: '/admin/complaints', filters: { status: 'open' },
        toForm: (r) => ({ status: r.status, resolution: r.resolution || '' }),
        update: (r, d) => ParkFlow.api.patch(`/admin/complaints/${r.id}`, { status: d.status, resolution: d.resolution || null }) })">
    <x-page-header title="Customer support" subtitle="Requests customers send from their account. Customers are notified in-app when the status changes.">
        <div><label class="sr-only" for="cp-s">Status</label><select id="cp-s" class="input" x-model="filters.status" @change="search()"><option value="">All</option><option value="open">Open</option><option value="in_review">In review</option><option value="resolved">Resolved</option><option value="closed">Closed</option></select></div>
    </x-page-header>
    <div class="summary-strip" x-show="loaded"><div class="kpi"><span class="kpi-value" x-text="total || rows.length"></span><span class="kpi-label">Support requests</span></div><div class="kpi"><span class="kpi-value text-warning-ink" x-text="rows.filter(c => ['open','in_review'].includes(c.status)).length"></span><span class="kpi-label">Open on this page</span></div></div>
    <div class="card"><div class="table-wrap"><table class="tbl"><thead><tr><th>Reference</th><th>Subject</th><th>Status</th><th>Received</th><th></th></tr></thead><tbody>
        <x-list-states cols="5" empty="No requests."/>
        <template x-for="c in rows" :key="c.id"><tr><td class="font-mono text-xs" x-text="c.reference"></td><td><span class="font-semibold" x-text="c.subject"></span><span class="block max-w-xl text-sm text-ink-muted" x-text="c.description"></span></td>
            <td><x-state kind="complaint" value="c.status"/></td><td x-text="$fmt.dateTime(c.created_at)"></td><td class="text-right"><button type="button" class="btn-ghost btn-sm" @click="openEdit(c)">Respond</button></td></tr></template>
    </tbody></table></div><x-pager/></div>
    <x-modal show="modal" title=":`Request ${current?.reference ?? ''}`">
        <div class="mb-3 rounded-lg bg-canvas p-3 text-sm"><p class="font-semibold" x-text="current?.subject"></p><p class="mt-1 whitespace-pre-line text-ink-muted" x-text="current?.description"></p></div>
        <form id="cp-f" class="space-y-3" @submit.prevent="save()">
            <div><label class="label" for="cp-st">Status</label><select id="cp-st" class="input" x-model="f.status"><option value="open">Open</option><option value="in_review">In review</option><option value="resolved">Resolved</option><option value="closed">Closed</option></select></div>
            <div><label class="label" for="cp-r">Reply / resolution (visible to the customer)</label><textarea id="cp-r" class="input min-h-28" x-model="f.resolution" maxlength="5000"></textarea></div></form>
        <x-slot:footer><button type="button" class="btn-secondary" @click="modal = false">Cancel</button><button form="cp-f" class="btn-primary" :disabled="busy">Save</button></x-slot:footer>
    </x-modal>
</div>
</x-layouts.app>
