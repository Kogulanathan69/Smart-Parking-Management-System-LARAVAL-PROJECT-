<x-layouts.app title="Refunds">
<div x-data="dataTable({ url: '/admin/refunds', filters: { status: 'pending' } })">
    <x-page-header title="Refunds" subtitle="Refunds come from overpayments, early exits and cancelled reservations. Approve, reject, then mark paid once the money has actually been returned.">
        <div><label class="sr-only" for="rf-s">Status</label><select id="rf-s" class="input" x-model="filters.status" @change="search()"><option value="">All</option><option value="pending">Pending approval</option><option value="approved">Approved — to pay</option><option value="paid">Paid out</option><option value="rejected">Rejected</option></select></div>
    </x-page-header>
    <div class="card"><div class="table-wrap"><table class="tbl"><thead><tr><th>Reference</th><th>Session</th><th class="tbl-num">Amount</th><th>Reason</th><th>Status</th><th>Requested</th><th></th></tr></thead><tbody>
        <x-list-states cols="7" empty="No refunds in this status."/>
        <template x-for="r in rows" :key="r.id"><tr><td class="font-mono text-xs" x-text="r.reference"></td><td x-text="r.session_id ? `#${r.session_id}` : '—'"></td><td class="tbl-num font-semibold" x-text="$fmt.money(r.amount_minor)"></td><td class="text-sm" x-text="r.reason || '—'"></td>
            <td><x-state kind="refund" value="r.status"/></td><td x-text="$fmt.dateTime(r.created_at)"></td>
            <td class="whitespace-nowrap text-right" x-data="{ decide(d, label, tone) { ParkFlow.confirm({ title: `${label} refund ${r.reference}?`, body: `${$fmt.money(r.amount_minor)}. The decision is recorded with your name.`, confirmLabel: label, tone }).then(ok => ok && ParkFlow.api.post(`/admin/refunds/${r.id}/decision`, { decision: d }).then(() => { ParkFlow.toast('Refund updated.'); load(); }).catch(e => ParkFlow.toast(e.message, 'danger'))) } }">
                <template x-if="r.status === 'pending'"><span class="flex justify-end gap-1"><button class="btn-secondary btn-sm" @click="decide('approve', 'Approve', 'primary')">Approve</button><button class="btn-ghost btn-sm" @click="decide('reject', 'Reject', 'danger')">Reject</button></span></template>
                <template x-if="r.status === 'approved'"><button class="btn-secondary btn-sm" @click="decide('mark_paid', 'Mark paid', 'primary')">Mark paid</button></template></td></tr></template>
    </tbody></table></div><x-pager/></div>
</div>
</x-layouts.app>
