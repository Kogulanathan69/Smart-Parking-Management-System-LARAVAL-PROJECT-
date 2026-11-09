<x-layouts.app title="Notifications">
<div x-data="dataTable({ url: '/notifications', perPage: 20, syncUrl: false })">
    <x-page-header title="Notifications" subtitle="Messages about your parking, receipts and operations. Only in-app notifications are sent (no SMS or email provider is configured).">
        <button type="button" class="btn-secondary" :disabled="!rows.some(n => !n.read_at)" @click="ParkFlow.api.post('/notifications/read-all').then(() => { rows.forEach(n => n.read_at = n.read_at || new Date().toISOString()); window.dispatchEvent(new CustomEvent('pf:notifications')) })">Mark all as read</button>
    </x-page-header>
    <div class="card">
        <table class="tbl"><thead class="sr-only"><tr><th>Notification</th><th>Action</th></tr></thead><tbody>
            <x-list-states cols="2" empty="You have no notifications."/>
            <template x-for="n in rows" :key="n.id">
                <tr :class="!n.read_at && 'bg-brand-faint/50'">
                    <td><p class="font-semibold" :class="n.read_at ? 'text-ink-muted' : 'text-ink'"><span x-show="!n.read_at" class="chip-brand mr-2">New</span><span x-text="n.data?.title || $fmt.titleCase(n.data?.kind || 'Notification')"></span></p>
                        <p class="text-sm text-ink-muted" x-text="n.data?.message || ''"></p>
                        <p class="mt-1 text-xs text-ink-faint" x-text="$fmt.dateTime(n.created_at)"></p></td>
                    <td class="w-40 text-right">
                        <template x-if="n.data?.receipt_number && $store.app.can('customer')"><a class="btn-ghost btn-sm" :href="`/my/receipts/${n.data.receipt_number}`">View receipt</a></template>
                        <button type="button" class="btn-ghost btn-sm" x-show="!n.read_at" @click="ParkFlow.api.post(`/notifications/${n.id}/read`).then(() => { n.read_at = new Date().toISOString(); window.dispatchEvent(new CustomEvent('pf:notifications')) })">Mark read</button></td>
                </tr>
            </template>
        </tbody></table>
        <x-pager/>
    </div>
</div>
</x-layouts.app>
