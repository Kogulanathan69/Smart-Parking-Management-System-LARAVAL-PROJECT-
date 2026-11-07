<x-layouts.app title="Calendar" wide>
<div x-data="{ month: ParkFlow.fmt.todayLocal().slice(0, 7), d: null, error: null, day: null,
    async load() { this.error = null; try { this.d = (await ParkFlow.api.get('/reports/calendar', { month: this.month })).data } catch (e) { this.error = e.message } },
    init() { this.load() },
    get lead() { return this.d ? (new Date(this.d.from + 'T00:00:00').getDay() + 6) % 7 : 0 },
    get max() { return Math.max(1, ...(this.d?.days || []).map(x => x.revenue_minor)) } }">
    <x-page-header title="Calendar" subtitle="Daily entries, exits, vehicles parked at end of day and revenue (Sri Lanka days).">
        <form class="flex items-end gap-2" @submit.prevent="load()"><div><label class="label" for="cal-m">Month</label><input id="cal-m" type="month" class="input" x-model="month"></div><button class="btn-primary">Show</button></form>
    </x-page-header>
    <template x-if="error"><div class="alert-danger" x-text="error"></div></template>
    <div class="alert-info mb-4" x-show="d">Select any day to inspect its server-calculated activity and revenue summary.</div>
    <div class="card overflow-hidden" x-show="d">
        <div class="hidden grid-cols-7 border-b border-line bg-canvas text-center text-xs font-semibold uppercase text-ink-muted md:grid"><template x-for="w in ['Mon','Tue','Wed','Thu','Fri','Sat','Sun']" :key="w"><div class="py-2" x-text="w"></div></template></div>
        <div class="grid grid-cols-1 md:grid-cols-7">
            <template x-for="i in lead" :key="'b'+i"><div class="hidden border-b border-r border-line bg-canvas/50 md:block"></div></template>
            <template x-for="x in d?.days || []" :key="x.date">
                <button type="button" class="min-h-24 border-b border-r border-line p-2 text-left hover:bg-brand-faint" @click="day = x" :aria-label="`${$fmt.date(x.date)}: ${x.entries} check-ins, ${x.exits} check-outs, revenue ${$fmt.money(x.revenue_minor)}`">
                    <span class="text-sm font-semibold" x-text="$fmt.date(x.date).slice(0, 6)"></span>
                    <span class="mt-1 block text-xs text-ink-muted" x-text="`In ${x.entries} · Out ${x.exits} · Parked ${x.parked_at_end_of_day}`"></span>
                    <span class="mt-1 block h-1.5 rounded-full bg-neutral"><span class="block h-full rounded-full bg-brand-strong" :style="`width:${Math.round(x.revenue_minor / max * 100)}%`"></span></span>
                    <span class="mt-1 block text-xs font-semibold tabular-nums" x-text="$fmt.money(x.revenue_minor)"></span>
                </button>
            </template>
        </div>
    </div>
    <x-modal show="day" close="day = null" title=":day ? $fmt.date(day.date) : ''">
        <dl class="dl"><dt>Check-ins</dt><dd x-text="day?.entries"></dd><dt>Check-outs</dt><dd x-text="day?.exits"></dd><dt>Parked at end of day</dt><dd x-text="day?.parked_at_end_of_day"></dd><dt>Revenue</dt><dd x-text="$fmt.money(day?.revenue_minor)"></dd><dt>Penalties applied</dt><dd x-text="$fmt.money(day?.penalties_minor)"></dd></dl>
        <div class="mt-4 flex flex-wrap gap-2"><a class="btn-secondary btn-sm" :href="`{{ route('reports.sessions') }}?date=${day?.date}&status=`">Sessions that day</a></div>
    </x-modal>
</div>
</x-layouts.app>
