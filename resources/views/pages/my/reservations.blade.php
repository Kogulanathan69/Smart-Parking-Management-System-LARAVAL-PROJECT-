<x-layouts.app title="My reservations">
<div x-data="dataTable({ url: '/customer/reservations', syncUrl: false })">
    <x-page-header title="My reservations" subtitle="Weekly and monthly packages keep a dedicated slot for your vehicle. They start at your first Check-In on that package; ask staff to renew."/>
    <div class="grid gap-3 md:grid-cols-2"><template x-for="r in rows" :key="r.id"><a :href="`/my/reservations/${r.id}`" class="card card-pad hover:border-brand-strong hover:no-underline">
        <div class="flex items-start justify-between gap-2"><span class="plate" x-text="r.vehicle?.identifier"></span><x-state kind="reservation" value="r.status"/></div>
        <p class="mt-2 text-sm text-ink" x-text="`${r.package?.name} · Zone ${r.zone?.code}, slot ${r.slot?.code}`"></p>
        <p class="text-sm text-ink-muted" x-text="`Until ${$fmt.dateTime(r.expires_at)}`"></p></a></template></div>
    <template x-if="empty"><div class="card card-pad text-ink-muted">You have no reservations.</div></template>
    <template x-if="error"><div class="alert-danger" x-text="error"></div></template><x-pager/>
</div>
</x-layouts.app>
