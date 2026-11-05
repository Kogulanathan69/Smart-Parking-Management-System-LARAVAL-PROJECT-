<x-layouts.app title="Receipts">
<div x-data="dataTable({ url: '/customer/receipts', syncUrl: false })">
    <x-page-header title="Receipts" subtitle="Receipts for completed parking sessions."/>
    <div class="card"><div class="table-wrap"><table class="tbl"><thead><tr><th>Receipt</th><th>Session</th><th>Vehicle</th><th>Issued</th><th class="tbl-num">Paid</th></tr></thead><tbody>
        <x-list-states cols="5" empty="No receipts yet."/>
        <template x-for="r in rows" :key="r.number"><tr><td><a :href="`/my/receipts/${r.number}`" class="font-mono" x-text="r.number"></a></td><td x-text="r.session_reference || '—'"></td><td x-text="r.vehicle || '—'"></td><td x-text="$fmt.dateTime(r.issued_at)"></td><td class="tbl-num" x-text="$fmt.money(r.paid_minor)"></td></tr></template>
    </tbody></table></div><x-pager/></div>
</div>
</x-layouts.app>
