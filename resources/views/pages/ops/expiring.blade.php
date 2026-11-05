<x-layouts.app title="Expiring & overstay">
<div x-data="dataTable({ url: '/staff/sessions/expiring', filters: { within: '60' }, perPage: 50 })">
    <x-page-header title="Expiring & overstay" subtitle="Open sessions whose package ends soon, plus sessions already past their package end. Customers with accounts get in-app reminders automatically.">
        <form class="flex items-end gap-2" @submit.prevent="search()"><div><label class="label" for="ex-w">Ending within</label>
            <select id="ex-w" class="input" x-model="filters.within" @change="search()"><option value="30">30 minutes</option><option value="60">1 hour</option><option value="180">3 hours</option><option value="1440">24 hours</option></select></div></form>
    </x-page-header>
    @include('pages.ops._session-table', ['link' => '/ops/sessions/'])
</div>
</x-layouts.app>
