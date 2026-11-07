<x-layouts.app title="Users" wide>
<div x-data="crudPage({ url: '/admin/users', filters: { q: '', role: '', status: '' },
        blank: { name: '', email: '', phone: '', password: '', password_confirmation: '', role: 'staff' },
        create: (d) => ParkFlow.api.post('/admin/users', d) })">
    <x-page-header title="Users" subtitle="Staff, admin and auditor accounts are created here only. Customers can also register themselves.">
        <button type="button" class="btn-primary" @click="openCreate()"><x-icon name="plus" class="h-4 w-4"/>Create account</button>
    </x-page-header>
    <form class="card mb-4 grid gap-3 p-4 sm:grid-cols-4" @submit.prevent="search()">
        <div class="filter-heading"><span class="filter-heading-icon"><x-icon name="users"/></span><div><h2 class="card-title">Account directory</h2><p class="text-xs text-ink-muted">Search and filter authorized ParkFlow accounts.</p></div></div>
        <div class="sm:col-span-2"><label class="label" for="u-q">Name, email or phone</label><input id="u-q" class="input" x-model="filters.q"></div>
        <div><label class="label" for="u-r">Role</label><select id="u-r" class="input" x-model="filters.role"><option value="">All</option>@foreach (\App\Models\User::ROLES as $r)<option value="{{ $r }}">{{ \Illuminate\Support\Str::headline($r) }}</option>@endforeach</select></div>
        <div><label class="label" for="u-s">Status</label><select id="u-s" class="input" x-model="filters.status"><option value="">All</option><option value="active">Active</option><option value="inactive">Inactive</option><option value="suspended">Suspended</option></select></div>
        <div class="flex items-end gap-2 sm:col-span-4"><button class="btn-primary">Search accounts</button><button type="button" class="btn-secondary" @click="reset()">Reset</button><span class="ml-auto text-sm text-ink-muted" x-show="loaded" x-text="summary"></span></div>
    </form>
    <div class="card"><div class="table-wrap"><table class="tbl"><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>2-step</th><th>Last sign-in</th><th>Created</th></tr></thead><tbody>
        <x-list-states cols="7" empty="No accounts match."/>
        <template x-for="u in rows" :key="u.id"><tr>
            <td><a :href="`/manage/users/${u.id}`" class="font-semibold" x-text="u.name"></a></td><td x-text="u.email"></td><td x-text="$fmt.roleLabel(u.role)"></td><td><x-state kind="user" value="u.status"/></td>
            <td x-text="u.two_factor_enabled ? 'On' : 'Off'"></td><td x-text="$fmt.dateTime(u.last_login_at, 'Never')"></td><td x-text="$fmt.date(u.created_at)"></td></tr></template>
    </tbody></table></div><x-pager/></div>
    <x-modal show="modal" title="Create account">
        <form id="uc-f" class="grid gap-3 sm:grid-cols-2" @submit.prevent="save()">
            <x-xfield class="sm:col-span-2" label="Full name" model="f.name" name="name" form="$data" required/>
            <x-xfield label="Email" model="f.email" name="email" type="email" form="$data" required autocomplete="off"/>
            <x-xfield label="Mobile" model="f.phone" name="phone" type="tel" form="$data"/>
            <div class="sm:col-span-2"><label class="label" for="uc-r">Role</label><select id="uc-r" class="input" x-model="f.role">
                <option value="staff">Staff</option><option value="customer">Customer</option>
                <template x-if="$store.app.can('super_admin')"><optgroup label="Super Admin only"><option value="admin">Admin (max 2)</option><option value="auditor">Auditor</option></optgroup></template></select>
                <p class="field-error" x-show="err('role')" x-text="err('role')"></p>
                <p class="hint">Super Admin accounts are created only from the server console.</p></div>
            <x-xfield label="Temporary password" model="f.password" name="password" type="password" form="$data" required autocomplete="new-password"/>
            <x-xfield label="Confirm password" model="f.password_confirmation" name="password_confirmation" type="password" form="$data" required autocomplete="new-password"/>
            <p class="text-xs text-ink-muted sm:col-span-2">Share the password privately and ask the person to change it under My account after signing in.</p>
        </form>
        <x-slot:footer><button type="button" class="btn-secondary" @click="modal = false">Cancel</button><button form="uc-f" class="btn-primary" :disabled="busy">Create account</button></x-slot:footer>
    </x-modal>
</div>
</x-layouts.app>
