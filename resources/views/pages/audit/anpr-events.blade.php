<x-layouts.app title="Recognition history" wide>
<div x-data="dataTable({ url: '/staff/anpr/events', filters: { verification_status: '', direction: '', recognition_status: '', camera: '', plate: '' }, perPage: 50 })">
    <x-page-header title="Recognition history" subtitle="All plate readings with their verification outcome. Read-only."/>
    <div class="alert-info mb-4"><strong>Read-only recognition evidence.</strong> Verification decisions can only be made within authorized Staff gate workflows.</div>
    <form class="card mb-4 grid gap-3 p-4 sm:grid-cols-3 lg:grid-cols-6" @submit.prevent="search()">
        <div class="filter-heading"><span class="filter-heading-icon"><x-icon name="camera"/></span><div><h2 class="card-title">Recognition filters</h2><p class="text-xs text-ink-muted">Search historical readings without changing their verification state.</p></div></div>
        <div><label class="label" for="ae-v">Verification</label><select id="ae-v" class="input" x-model="filters.verification_status"><option value="">Any</option><option value="pending">Review required</option><option value="verified">Verified</option><option value="corrected">Corrected</option><option value="rejected">Rejected</option></select></div>
        <div><label class="label" for="ae-d">Direction</label><select id="ae-d" class="input" x-model="filters.direction"><option value="">Both</option><option value="entry">Check-In</option><option value="exit">Check-Out</option></select></div>
        <div><label class="label" for="ae-r">Recognition</label><select id="ae-r" class="input" x-model="filters.recognition_status"><option value="">Any</option>@foreach (['recognized','low_confidence','unreadable','engine_error','duplicate','manual'] as $st)<option value="{{ $st }}" x-text="$fmt.stateLabel('recognition','{{ $st }}')"></option>@endforeach</select></div>
        <div><label class="label" for="ae-c">Camera code</label><input id="ae-c" class="input" x-model="filters.camera"></div>
        <div><label class="label" for="ae-p">Plate</label><input id="ae-p" class="input" x-model="filters.plate"></div>
        <div class="flex items-end gap-2"><button class="btn-primary flex-1">Filter</button><button type="button" class="btn-secondary" @click="reset()">Reset</button></div>
    </form>
    @include('pages.ops._anpr-table', ['canAct' => false])
</div>
</x-layouts.app>
