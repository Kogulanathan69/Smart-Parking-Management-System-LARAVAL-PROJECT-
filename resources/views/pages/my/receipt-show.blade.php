<x-layouts.app title="Receipt" :crumbs="['Receipts' => route('my.receipts')]">
<div x-data="{ rc: null, loading: true, error: null, notFound: false, async reload() { try { this.rc = (await ParkFlow.api.get('/customer/receipts/{{ rawurlencode($number) }}')).data } catch (e) { this.error = e.message; this.notFound = [403, 404].includes(e.status) } finally { this.loading = false } }, init() { this.reload() } }">
    @include('pages.ops._record-states', ['var' => 'rc'])
    <template x-if="rc"><div class="max-w-3xl">@include('pages.partials._receipt')
        <button type="button" class="btn-primary mt-3 no-print" @click="window.print()"><x-icon name="printer" class="h-4 w-4"/>Print or save as PDF</button></div></template>
</div>
</x-layouts.app>
