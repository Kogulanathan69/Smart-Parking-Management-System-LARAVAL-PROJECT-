<x-layouts.app title="Parked vehicles" wide>
<div x-data="dataTable({ url: '/staff/sessions', filters: { q: '', status: '', vehicle_category: '', fuel_type: '', zone_id: '', payment_status: '', date: '' } })">
    <x-page-header title="Parked vehicles" subtitle="Open sessions by default. Search by plate or session reference; use the status filter for history.">
        <a href="{{ route('ops.check-in') }}" class="btn-primary"><x-icon name="arrow-in" class="h-4 w-4"/>Check-In</a>
    </x-page-header>
    @include('pages.ops._session-filters')
    @include('pages.ops._session-table', ['link' => '/ops/sessions/'])
</div>
</x-layouts.app>
