<x-layouts.app title="Session ledger" wide>
<div x-data="dataTable({ url: '/reports/sessions', filters: { q: '', status: '', vehicle_category: '', fuel_type: '', zone_id: '', payment_status: '', date: '' }, perPage: 50 })">
    <x-page-header title="Session ledger" subtitle="Check-In / Check-Out records. Without a date it covers the last 7 days.">
        <button type="button" class="btn-secondary no-print" @click="window.print()"><x-icon name="printer" class="h-4 w-4"/>Print</button>
        <button type="button" class="btn-secondary no-print" :disabled="!rows.length" @click="$fmt.downloadCsv(`sessions-p${page}.csv`, ['Reference','Plate','Category','Fuel','Status','Zone','Slot','Package','Check-In','Check-Out','Charge (LKR)','Receipt'], rows.map(s => [s.reference, s.vehicle?.plate_number || s.vehicle?.identifier, s.vehicle_category, s.fuel_type, s.status, s.zone?.code, s.slot?.code, s.package?.name, s.entry_at, s.exit_at, s.total_charge_minor != null ? (s.total_charge_minor/100).toFixed(2) : '', s.receipt_number]))"><x-icon name="download" class="h-4 w-4"/>CSV (this page)</button>
    </x-page-header>
    @include('pages.ops._session-filters', ['defaultStatusLabel' => 'Any status', 'dateLabel' => 'Date'])
    @include('pages.ops._session-table', ['link' => auth()->user()->hasAnyRole('staff','admin','super_admin') ? '/ops/sessions/' : ''])
</div>
</x-layouts.app>
