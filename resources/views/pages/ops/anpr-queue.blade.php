<x-layouts.app title="ANPR review queue" wide>
<div x-data="dataTable({ url: '/staff/anpr/events', filters: { verification_status: 'pending', direction: '', recognition_status: '', camera: '', plate: '', unused: '1' } })" x-init="setInterval(() => !loading && load(), 15000)">
    <x-page-header title="ANPR review queue" subtitle="Plate readings waiting for a decision. Nothing here has opened a gate or created a session.">
        <a href="{{ route('ops.check-in') }}" class="btn-secondary">Check-In</a><a href="{{ route('ops.check-out') }}" class="btn-secondary">Check-Out</a>
    </x-page-header>
    <form class="card mb-4 grid gap-3 p-4 sm:grid-cols-3 lg:grid-cols-7" @submit.prevent="search()">
        <div><label class="label" for="q-v">Verification</label><select id="q-v" class="input" x-model="filters.verification_status"><option value="">Any</option><option value="pending">Review required</option><option value="verified">Verified</option><option value="corrected">Corrected</option><option value="rejected">Rejected</option></select></div>
        <div><label class="label" for="q-d">Direction</label><select id="q-d" class="input" x-model="filters.direction"><option value="">Both</option><option value="entry">Check-In</option><option value="exit">Check-Out</option></select></div>
        <div><label class="label" for="q-r">Recognition</label><select id="q-r" class="input" x-model="filters.recognition_status"><option value="">Any</option>
            @foreach (['recognized','low_confidence','unreadable','engine_error','duplicate','manual'] as $st)<option value="{{ $st }}" x-text="$fmt.stateLabel('recognition','{{ $st }}')"></option>@endforeach</select></div>
        <div><label class="label" for="q-c">Camera code</label><input id="q-c" class="input" x-model="filters.camera" maxlength="40"></div>
        <div><label class="label" for="q-p">Plate</label><input id="q-p" class="input" x-model="filters.plate" maxlength="30"></div>
        <label class="flex items-end gap-2 pb-2 text-sm"><input type="checkbox" class="check" x-model="filters.unused" true-value="1" false-value=""> Unused only</label>
        <div class="flex items-end gap-2"><button class="btn-primary flex-1">Filter</button><button type="button" class="btn-secondary" @click="reset()">Reset</button></div>
    </form>
    @include('pages.ops._anpr-table', ['canAct' => true])
</div>
</x-layouts.app>
