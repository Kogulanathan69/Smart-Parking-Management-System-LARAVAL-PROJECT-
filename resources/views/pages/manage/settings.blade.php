<x-layouts.app title="Settings" wide>
<div x-data="settingsPage()">
    <x-page-header title="Settings" subtitle="Operating rules used by the server. Every change is versioned and written to the audit trail."/>
    <template x-if="error"><div class="alert-danger" x-text="error"></div></template>
    <div class="mb-4 flex flex-wrap gap-1" role="tablist">
        <button type="button" role="tab" class="btn-sm" :class="tab === 'all' ? 'btn-primary' : 'btn-secondary'" @click="tab = 'all'">All</button>
        <template x-for="g in groups" :key="g"><button type="button" role="tab" class="btn-sm" :class="tab === g ? 'btn-primary' : 'btn-secondary'" @click="tab = g" x-text="$fmt.titleCase(g)"></button></template>
    </div>
    <div class="grid gap-4 xl:grid-cols-3">
        <div class="card xl:col-span-2"><div class="table-wrap"><table class="tbl"><thead><tr><th>Setting</th><th>Value</th><th></th></tr></thead><tbody>
            <tr x-show="loading"><td colspan="3"><div class="skeleton h-4 w-1/2"></div></td></tr>
            <template x-for="r in visible()" :key="r.key"><tr><td><span class="font-semibold" x-text="meta[r.key]?.label || r.key"></span><span class="block font-mono text-xs text-ink-muted" x-text="r.key"></span></td>
                <td x-text="display(r)"></td><td class="text-right"><button type="button" class="btn-ghost btn-sm" x-show="r.editable" @click="edit(r)">Change</button><span x-show="!r.editable" class="text-xs text-ink-muted">Read-only for your role</span></td></tr></template>
        </tbody></table></div></div>
        <section class="card card-pad"><h2 class="card-title">Recent changes</h2>
            <ul class="mt-3 divide-y divide-line text-sm"><template x-for="(h, i) in history.slice(0, 25)" :key="i"><li class="py-2"><span class="font-semibold" x-text="meta[h.setting?.key]?.label || h.setting?.key"></span><p class="font-mono text-xs" x-text="`${JSON.stringify(h.old_value)} → ${JSON.stringify(h.new_value)}`"></p><p class="text-xs text-ink-muted" x-text="`${$fmt.dateTime(h.created_at)} · user #${h.changed_by}`"></p></li></template>
                <li x-show="!history.length" class="py-2 text-ink-muted">No changes yet; defaults are in use.</li></ul></section>
    </div>
    <x-modal show="editing" close="editing = null" title=":meta[editing?.key]?.label || editing?.key">
        <form id="st-f" class="space-y-3" @submit.prevent="save()">
            <p class="font-mono text-xs text-ink-muted" x-text="editing?.key"></p>
            <template x-if="meta[editing?.key]?.warn"><div class="alert-warning text-sm" x-text="meta[editing?.key]?.warn"></div></template>
            <template x-if="(meta[editing?.key]?.type || 'text') === 'bool'"><label class="flex items-center gap-2"><input type="checkbox" class="check" x-model="value"> <span x-text="value ? 'On' : 'Off'"></span></label></template>
            <template x-if="meta[editing?.key]?.type === 'enum'"><select class="input" x-model="value" aria-label="Value"><template x-for="o in meta[editing.key].options" :key="o"><option :value="o" x-text="$fmt.titleCase(o)"></option></template></select></template>
            <template x-if="meta[editing?.key]?.type === 'roles'"><div class="flex flex-wrap gap-3"><template x-for="o in meta[editing.key].options" :key="o"><label class="flex items-center gap-2 text-sm"><input type="checkbox" class="check" :value="o" x-model="value"><span x-text="$fmt.roleLabel(o)"></span></label></template></div></template>
            <template x-if="['int','money','time','intlist','text'].includes(meta[editing?.key]?.type || 'text')"><div><input class="input" x-model="value" :type="meta[editing?.key]?.type === 'time' ? 'time' : 'text'" :inputmode="['int','money'].includes(meta[editing?.key]?.type) ? 'decimal' : 'text'" aria-label="Value">
                <p class="hint" x-text="meta[editing?.key]?.type === 'intlist' ? `Comma-separated ${meta[editing.key].unit}` : (meta[editing?.key]?.type === 'money' ? 'LKR' : (meta[editing?.key]?.unit || ''))"></p></div></template>
            <p class="field-error" x-show="errors.value" x-text="errors.value?.[0]"></p></form>
        <x-slot:footer><button type="button" class="btn-secondary" @click="editing = null">Cancel</button><button form="st-f" class="btn-primary" :disabled="busy">Save</button></x-slot:footer>
    </x-modal>
</div>
</x-layouts.app>
