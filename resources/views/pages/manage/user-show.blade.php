<x-layouts.app title="User" :crumbs="['Users' => route('manage.users')]">
<div x-data="userDetail({{ (int) $id }})">
    @include('pages.ops._record-states', ['var' => 'u'])
    <template x-if="u"><div>
        <x-page-header title="Account">
            <button type="button" class="btn-secondary" x-show="!isSelf" @click="status = { open: true, value: u.status, reason: '' }">Change status</button>
            <button type="button" class="btn-secondary" x-show="!isSelf && $store.app.can('super_admin') && !['customer','super_admin'].includes(u.role)" @click="role = { open: true, value: u.role }">Change role</button>
        </x-page-header>
        <section class="card card-pad max-w-2xl"><div class="flex items-center justify-between gap-2"><h2 class="text-lg font-semibold" x-text="u.name"></h2><x-state kind="user" value="u.status"/></div>
            <dl class="dl mt-4"><dt>Email</dt><dd x-text="u.email"></dd><dt>Mobile</dt><dd x-text="u.phone || '—'"></dd><dt>Role</dt><dd x-text="$fmt.roleLabel(u.role)"></dd>
                <dt>Status reason</dt><dd x-text="u.status_reason || '—'"></dd><dt>Two-step</dt><dd x-text="u.two_factor_enabled ? 'On' : 'Off'"></dd>
                <dt>Last sign-in</dt><dd x-text="$fmt.dateTime(u.last_login_at, 'Never')"></dd><dt>Created</dt><dd x-text="$fmt.dateTime(u.created_at)"></dd></dl>
            <p class="mt-4 text-xs text-ink-muted" x-show="isSelf">You cannot change your own status or role.</p>
            <p class="mt-4 text-xs text-ink-muted">Name, email and password are managed by the account holder (My account, or Forgot password on the sign-in page).</p></section>
        <x-modal show="status.open" title="Change account status">
            <form id="us-f" class="space-y-3" @submit.prevent="saveStatus()">
                <div><label class="label" for="us-s">Status</label><select id="us-s" class="input" x-model="status.value"><option value="active">Active</option><option value="inactive">Inactive</option><option value="suspended">Suspended</option></select></div>
                <div><label class="label" for="us-r">Reason</label><input id="us-r" class="input" x-model="status.reason" maxlength="255"></div>
                <p class="text-xs text-ink-muted">Inactive or suspended users are signed out on their next request.</p></form>
            <x-slot:footer><button type="button" class="btn-secondary" @click="status.open = false">Cancel</button><button form="us-f" class="btn-primary" :disabled="busy">Save</button></x-slot:footer>
        </x-modal>
        <x-modal show="role.open" title="Change role">
            <form id="ur-f" class="space-y-3" @submit.prevent="saveRole()"><div><label class="label" for="ur-r">Role</label><select id="ur-r" class="input" x-model="role.value"><option value="admin">Admin</option><option value="staff">Staff</option><option value="auditor">Auditor</option></select></div></form>
            <x-slot:footer><button type="button" class="btn-secondary" @click="role.open = false">Cancel</button><button form="ur-f" class="btn-primary" :disabled="busy">Change role</button></x-slot:footer>
        </x-modal>
    </div></template>
</div>
</x-layouts.app>
