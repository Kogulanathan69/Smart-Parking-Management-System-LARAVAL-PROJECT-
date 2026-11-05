<x-layouts.app title="Safety tasks">
<div x-data="inspectionsPage()">
    <x-page-header title="Safety tasks" subtitle="Safety-package inspections due in the next 2 hours, oldest first."><button type="button" class="btn-secondary" @click="load()"><x-icon name="refresh" class="h-4 w-4"/>Refresh</button></x-page-header>
    <template x-if="error"><div class="alert-danger" x-text="error"></div></template>
    <div class="card"><div class="table-wrap"><table class="tbl"><thead><tr><th>Due</th><th>Type</th><th>Session</th><th>Status</th><th></th></tr></thead><tbody>
        <tr x-show="loading"><td colspan="5"><div class="skeleton h-4 w-1/2"></div></td></tr>
        <template x-for="i in due" :key="i.id"><tr><td class="whitespace-nowrap" x-text="$fmt.dateTime(i.scheduled_for)"></td><td x-text="$fmt.titleCase(i.type)"></td>
            <td><a :href="`/ops/sessions/${i.session_uuid}`" x-text="i.session_reference"></a></td><td><x-state kind="inspection" value="i.status"/></td>
            <td class="text-right"><button type="button" class="btn-primary btn-sm" @click="start(i)">Inspect</button></td></tr></template>
        <tr x-show="!loading && !due.length"><td colspan="5" class="py-8 text-center text-ink-muted">No inspections are due. Well done.</td></tr>
    </tbody></table></div></div>
    <x-modal show="current" close="current = null" title="Record inspection">
        <form id="ip-f" class="space-y-3" @submit.prevent="submit()">
            <p class="text-sm" x-text="`Session ${current?.session_reference} · ${$fmt.titleCase(current?.type)} inspection`"></p>
            <div><label class="label" for="ip-c">Condition</label><select id="ip-c" class="input" x-model="form.condition"><option value="good">Good</option><option value="minor_damage">Minor damage</option><option value="major_damage">Major damage</option></select></div>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="check" x-model="form.new_damage_found"> New damage found</label>
            <div><label class="label" for="ip-n">Notes</label><textarea id="ip-n" class="input" x-model="form.notes" maxlength="2000"></textarea></div>
            <div><label class="label" for="ip-p">Photo evidence (optional)</label><input id="ip-p" type="file" class="input" accept=".jpg,.jpeg,.png,.webp,image/*" capture="environment" @change="file = $event.target.files[0] || null"><p class="hint">JPG, PNG or WebP, up to 5 MB. Stored privately.</p></div>
        </form>
        <x-slot:footer><button type="button" class="btn-secondary" @click="current = null">Cancel</button><button form="ip-f" class="btn-primary" :disabled="busy">Save inspection</button></x-slot:footer>
    </x-modal>
</div>
</x-layouts.app>
