<x-layouts.app title="ANPR corrections" wide>
<div x-data="dataTable({ url: '/audit/anpr/corrections', perPage: 50 })">
    <x-page-header title="ANPR corrections & overrides" subtitle="Every time staff corrected or rejected a plate reading, or identified a vehicle manually.">
        <button type="button" class="btn-secondary no-print" :disabled="!rows.length" @click="$fmt.downloadCsv(`anpr-corrections-p${page}.csv`, ['When','Action','From','To','Reason','Notes','Staff','Role','Event','Direction'], rows.map(r => [r.created_at, r.action, r.from_plate, r.to_plate, r.reason, r.notes, r.user?.name, r.user?.role, r.event?.uuid, r.event?.direction]))"><x-icon name="download" class="h-4 w-4"/>CSV (this page)</button>
    </x-page-header>
    <div class="alert-info mb-4"><strong>Read-only verification history.</strong> Corrections are recorded from Staff ANPR decisions and cannot be changed here.</div>
    <div class="card"><div class="table-wrap"><table class="tbl"><thead><tr><th>When</th><th>Action</th><th>Read as</th><th>Changed to</th><th>Reason</th><th>Staff</th><th>Direction</th></tr></thead><tbody>
        <x-list-states cols="7" empty="No corrections or overrides recorded."/>
        <template x-for="r in rows" :key="r.id"><tr><td class="whitespace-nowrap" x-text="$fmt.dateTime(r.created_at)"></td><td x-text="({ correct: 'Corrected', reject: 'Rejected', manual_create: 'Manual identification' })[r.action] || r.action"></td>
            <td class="font-mono" x-text="r.from_plate || r.event?.raw_text || '—'"></td><td class="font-mono" x-text="r.to_plate || '—'"></td><td><span x-text="$fmt.titleCase(r.reason) || '—'"></span><span class="block text-xs text-ink-muted" x-text="r.notes || ''"></span></td>
            <td x-text="r.user ? `${r.user.name} (${$fmt.roleLabel(r.user.role)})` : '—'"></td><td x-text="r.event?.direction === 'entry' ? 'Check-In' : (r.event?.direction === 'exit' ? 'Check-Out' : '—')"></td></tr></template>
    </tbody></table></div><x-pager/></div>
</div>
</x-layouts.app>
