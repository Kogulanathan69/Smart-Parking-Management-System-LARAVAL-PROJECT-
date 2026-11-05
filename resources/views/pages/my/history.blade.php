<x-layouts.app title="Parking history" wide>
<div x-data="dataTable({ url: '/customer/sessions', filters: { status: '', from: '', to: '', plate: '' }, perPage: 20 })">
    <x-page-header title="Parking history" subtitle="All parking sessions linked to your account."/>
    <form class="card mb-4 grid gap-3 p-4 sm:grid-cols-5" @submit.prevent="search()">
        <div><label class="label" for="mh-p">Plate</label><input id="mh-p" class="input" x-model="filters.plate"></div>
        <div><label class="label" for="mh-s">Status</label><select id="mh-s" class="input" x-model="filters.status"><option value="">All</option><option value="active">Parked</option><option value="completed">Checked out</option></select></div>
        <div><label class="label" for="mh-f">From</label><input id="mh-f" type="date" class="input" x-model="filters.from"></div>
        <div><label class="label" for="mh-t">To</label><input id="mh-t" type="date" class="input" x-model="filters.to"></div>
        <div class="flex items-end gap-2"><button class="btn-primary flex-1">Filter</button><button type="button" class="btn-secondary" @click="reset()">Reset</button></div>
    </form>
    @include('pages.ops._session-table', ['link' => '/my/history/'])
</div>
</x-layouts.app>
