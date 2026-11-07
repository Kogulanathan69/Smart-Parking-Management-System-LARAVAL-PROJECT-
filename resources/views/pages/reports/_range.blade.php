<form class="flex flex-wrap items-end gap-2 rounded-xl border border-line bg-surface p-3 shadow-sm no-print" @submit.prevent="search()">
    <div><label class="label" for="rg-f">From</label><input id="rg-f" type="date" class="input" x-model="filters.from"></div>
    <div><label class="label" for="rg-t">To</label><input id="rg-t" type="date" class="input" x-model="filters.to"></div>
    {{ $slot ?? '' }}
    <button class="btn-primary">Apply</button>
    <button type="button" class="btn-secondary" @click="window.print()"><x-icon name="printer" class="h-4 w-4"/>Print</button>
</form>
