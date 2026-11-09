<x-layouts.app title="Support request" :crumbs="['Support' => route('my.support')]">
<div x-data="{ c: null, loading: true, error: null, notFound: false, async reload() { try { this.c = (await ParkFlow.api.get('/customer/complaints/{{ (int) $id }}')).data } catch (e) { this.error = e.message; this.notFound = [403, 404].includes(e.status) } finally { this.loading = false } }, init() { this.reload() } }">
    @include('pages.ops._record-states', ['var' => 'c'])
    <template x-if="c"><section class="card card-pad max-w-3xl"><div class="flex items-start justify-between gap-2"><div><p class="font-mono text-xs text-ink-muted" x-text="c.reference"></p><h2 class="text-lg font-semibold" x-text="c.subject"></h2></div><x-state kind="complaint" value="c.status"/></div>
        <p class="mt-3 whitespace-pre-line" x-text="c.description"></p><p class="mt-2 text-xs text-ink-muted" x-text="`Sent ${$fmt.dateTime(c.created_at)}`"></p>
        <div class="mt-4 rounded-lg border border-line bg-canvas p-4"><p class="section-title">Reply from the parking office</p><p class="mt-1 whitespace-pre-line" x-text="c.resolution || 'No reply yet.'"></p><p class="mt-1 text-xs text-ink-muted" x-show="c.resolved_at" x-text="`Resolved ${$fmt.dateTime(c.resolved_at)}`"></p></div></section></template>
</div>
</x-layouts.app>
