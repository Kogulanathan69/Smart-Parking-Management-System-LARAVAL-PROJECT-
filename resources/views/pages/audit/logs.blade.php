<x-layouts.app title="Audit trail" wide>
<div x-data="dataTable({ url: '/audit/logs', filters: { action: '', actor_id: '', from: '', to: '' }, perPage: 50 })">
    <x-page-header title="Audit trail" subtitle="Every significant action, who did it and when. Entries cannot be edited or deleted.">
        <button type="button" class="btn-secondary no-print" :disabled="!rows.length" @click="$fmt.downloadCsv(`audit-p${page}.csv`, ['When','Action','Actor','Role','Subject','Subject ID','Old','New','Meta','IP'], rows.map(l => [l.created_at, l.action, l.actor?.name, l.actor_role, l.subject_type, l.subject_id, JSON.stringify(l.old_values), JSON.stringify(l.new_values), JSON.stringify(l.meta), l.ip_address]))"><x-icon name="download" class="h-4 w-4"/>CSV (this page)</button>
    </x-page-header>
    <div class="alert-info mb-4"><strong>Read-only audit evidence.</strong> Entries cannot be edited or removed from this interface.</div>
    <form class="card mb-4 grid gap-3 p-4 sm:grid-cols-6" @submit.prevent="search()">
        <div class="filter-heading"><span class="filter-heading-icon"><x-icon name="key"/></span><div><h2 class="card-title">Audit filters</h2><p class="text-xs text-ink-muted">Filter immutable activity records.</p></div></div>
        <div class="sm:col-span-2"><label class="label" for="al-a">Action starts with</label><input id="al-a" class="input font-mono" x-model="filters.action" placeholder="e.g. anpr. or payment."></div>
        <div><label class="label" for="al-u">Actor user ID</label><input id="al-u" class="input" inputmode="numeric" x-model="filters.actor_id"></div>
        <div><label class="label" for="al-f">From</label><input id="al-f" type="date" class="input" x-model="filters.from"></div>
        <div><label class="label" for="al-t">To</label><input id="al-t" type="date" class="input" x-model="filters.to"></div>
        <div class="flex items-end gap-2"><button class="btn-primary flex-1">Apply</button><button type="button" class="btn-secondary" @click="reset()">Reset</button></div>
    </form>
    <div class="card"><div class="table-wrap"><table class="tbl"><thead><tr><th>When</th><th>Action</th><th>Actor</th><th>Subject</th><th>Details</th></tr></thead><tbody>
        <x-list-states cols="5" empty="No audit entries match."/>
        <template x-for="l in rows" :key="l.id"><tr x-data="{ open: false }"><td class="whitespace-nowrap" x-text="$fmt.dateTime(l.created_at)"></td><td class="font-mono text-xs" x-text="l.action"></td>
            <td x-text="l.actor ? `${l.actor.name} (${$fmt.roleLabel(l.actor_role)})` : 'System'"></td><td x-text="l.subject_type ? `${l.subject_type} #${l.subject_id}` : '—'"></td>
            <td><button type="button" class="btn-ghost btn-sm" @click="open = !open" :aria-expanded="open.toString()" x-show="l.old_values || l.new_values || l.meta">Details</button>
                <pre x-show="open" x-cloak class="mt-2 max-w-xl overflow-x-auto whitespace-pre-wrap rounded bg-canvas p-2 text-xs" x-text="JSON.stringify({ before: l.old_values, after: l.new_values, meta: l.meta }, null, 2)"></pre></td></tr></template>
    </tbody></table></div><x-pager/></div>
</div>
</x-layouts.app>
