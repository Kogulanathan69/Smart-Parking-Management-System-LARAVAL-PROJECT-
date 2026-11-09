<x-layouts.app title="Support">
<div x-data="{ open: false }">
<div x-data="crudPage({ url: '/customer/complaints', syncUrl: false, blank: { subject: '', description: '', parking_session_uuid: new URLSearchParams(location.search).get('session') || '' },
        create: (d) => ParkFlow.api.post('/customer/complaints', { ...d, parking_session_uuid: d.parking_session_uuid || null }) })" x-init="if (new URLSearchParams(location.search).get('session')) openCreate()">
    <x-page-header title="Support" subtitle="Questions or problems with a parking session, bill or receipt. The parking office replies here.">
        <button type="button" class="btn-primary" @click="openCreate()"><x-icon name="plus" class="h-4 w-4"/>New request</button>
    </x-page-header>
    <div class="card"><div class="table-wrap"><table class="tbl"><thead><tr><th>Reference</th><th>Subject</th><th>Status</th><th>Sent</th></tr></thead><tbody>
        <x-list-states cols="4" empty="You have not sent any requests."/>
        <template x-for="c in rows" :key="c.id"><tr><td><a :href="`/my/support/${c.id}`" class="font-mono text-xs" x-text="c.reference"></a></td><td x-text="c.subject"></td><td><x-state kind="complaint" value="c.status"/></td><td x-text="$fmt.dateTime(c.created_at)"></td></tr></template>
    </tbody></table></div><x-pager/></div>
    <x-modal show="modal" title="New support request">
        <form id="sp-f" class="space-y-3" @submit.prevent="save()">
            <x-xfield label="Subject" model="f.subject" name="subject" form="$data" required/>
            <x-xfield label="Describe the issue" model="f.description" name="description" type="textarea" form="$data" required/>
            <p class="text-xs text-ink-muted" x-show="f.parking_session_uuid">This request is linked to the parking session you opened.</p>
        </form>
        <x-slot:footer><button type="button" class="btn-secondary" @click="modal = false">Cancel</button><button form="sp-f" class="btn-primary" :disabled="busy">Send</button></x-slot:footer>
    </x-modal>
</div></div>
</x-layouts.app>
