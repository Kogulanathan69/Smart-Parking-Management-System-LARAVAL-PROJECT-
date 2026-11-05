<x-layouts.app title="Package" :crumbs="['Packages & rates' => route('manage.packages')]" wide>
<div x-data="packageDetail({{ (int) $id }})">
    @include('pages.ops._record-states', ['var' => 'p'])
    <template x-if="p"><div>
        <x-page-header title="Package"><button type="button" class="btn-secondary" @click="startEdit()">Edit package</button><button type="button" class="btn-primary" @click="openRate()">Publish rate</button></x-page-header>
        <div class="grid gap-4 xl:grid-cols-3">
            <section class="card card-pad"><div class="flex items-center justify-between"><h2 class="text-lg font-semibold" x-text="p.name"></h2><span :class="p.is_active ? 'chip-success' : 'chip-neutral'" x-text="p.is_active ? 'Active' : 'Inactive'"></span></div>
                <dl class="dl mt-3"><dt>Code</dt><dd class="font-mono" x-text="p.code"></dd><dt>Billing</dt><dd x-text="$fmt.titleCase(p.billing_mode)"></dd><dt>Duration</dt><dd x-text="p.duration_minutes ? $fmt.minutes(p.duration_minutes) : '—'"></dd>
                    <dt>Zone types</dt><dd x-text="(p.zone_types || []).map($fmt.titleCase).join(', ') || '—'"></dd><dt>Guaranteed slot</dt><dd x-text="p.guarantees_slot ? 'Yes' : 'No'"></dd><dt>Early-exit credit</dt><dd x-text="p.allows_early_exit_credit ? 'Yes' : 'No'"></dd><dt>Description</dt><dd x-text="p.description || '—'"></dd></dl></section>
            <section class="card xl:col-span-2"><h2 class="card-title px-4 pt-4">Rate history (all versions)</h2>
                <div class="table-wrap mt-2"><table class="tbl"><thead><tr><th>Vehicle</th><th>Version</th><th class="tbl-num">Price</th><th class="tbl-num">Overtime / h</th><th class="tbl-num">Daily cap</th><th class="tbl-num">Grace</th><th>From</th><th>To</th><th></th></tr></thead><tbody>
                    <template x-for="r in rates" :key="r.id"><tr :class="r.effective_to ? 'text-ink-muted' : ''"><td x-text="catName(r.vehicle_category_id)"></td><td x-text="`v${r.version}`"></td><td class="tbl-num" x-text="$fmt.money(r.price_minor)"></td><td class="tbl-num" x-text="$fmt.money(r.overtime_hourly_minor)"></td><td class="tbl-num" x-text="$fmt.money(r.overtime_daily_cap_minor)"></td><td class="tbl-num" x-text="`${r.grace_minutes ?? 0}m`"></td><td x-text="$fmt.dateTime(r.effective_from)"></td><td x-text="r.effective_to ? $fmt.dateTime(r.effective_to) : 'Current'"></td>
                        <td><button type="button" class="btn-ghost btn-sm" x-show="!r.effective_to" @click="openRate(r)">New version</button></td></tr></template>
                    <tr x-show="!rates.length"><td colspan="9" class="py-4 text-center text-ink-muted">No rates yet.</td></tr></tbody></table></div></section>
        </div>
        <x-modal show="rateOpen" title="Publish a new rate version">
            <form id="rt-f" class="grid gap-3 sm:grid-cols-2" @submit.prevent="publishRate()">
                <div class="sm:col-span-2"><label class="label" for="rt-c">Vehicle category</label><select id="rt-c" class="input" x-model="rate.vehicle_category_id" required><option value="">Choose…</option><template x-for="c in ref?.vehicle_categories || []" :key="c.id"><option :value="c.id" x-text="c.name"></option></template></select><p class="field-error" x-show="rateErrors.vehicle_category_id" x-text="rateErrors.vehicle_category_id?.[0]"></p></div>
                <div><label class="label" for="rt-p">Price (LKR)</label><input id="rt-p" class="input" inputmode="decimal" x-model="rate.price" required><p class="field-error" x-show="rateErrors.price_minor" x-text="rateErrors.price_minor?.[0]"></p></div>
                <div><label class="label" for="rt-o">Overtime per hour (LKR)</label><input id="rt-o" class="input" inputmode="decimal" x-model="rate.overtime"></div>
                <div><label class="label" for="rt-cap">Overtime daily cap (LKR)</label><input id="rt-cap" class="input" inputmode="decimal" x-model="rate.cap"></div>
                <div><label class="label" for="rt-g">Grace (minutes)</label><input id="rt-g" type="number" min="0" max="1440" class="input" x-model="rate.grace_minutes"></div>
                <div class="sm:col-span-2"><label class="label" for="rt-e">Effective from (optional)</label><input id="rt-e" type="datetime-local" class="input" x-model="rate.effective_from"><p class="hint">Leave empty to apply from now. Sri Lanka time.</p><p class="field-error" x-show="rateErrors.effective_from" x-text="rateErrors.effective_from?.[0]"></p></div>
            </form>
            <x-slot:footer><button type="button" class="btn-secondary" @click="rateOpen = false">Cancel</button><button form="rt-f" class="btn-primary" :disabled="busy">Publish</button></x-slot:footer>
        </x-modal>
        <x-modal show="edit" title="Edit package">
            <form id="pe-f" class="grid gap-3" @submit.prevent="save()">
                <div><label class="label" for="pe-n">Name</label><input id="pe-n" class="input" x-model="f.name" maxlength="100"></div>
                <fieldset><legend class="label">Allowed in zone types</legend><div class="flex flex-wrap gap-3"><template x-for="t in ref?.zone_types || []" :key="t.id"><label class="flex items-center gap-2 text-sm"><input type="checkbox" class="check" :value="t.id" x-model.number="f.zone_type_ids"><span x-text="t.name"></span></label></template></div></fieldset>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="check" x-model="f.guarantees_slot"> Guarantees a dedicated slot</label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="check" x-model="f.allows_early_exit_credit"> Early exit earns credit</label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="check" x-model="f.is_active"> Active</label>
                <div><label class="label" for="pe-d">Description</label><textarea id="pe-d" class="input" x-model="f.description" maxlength="1000"></textarea></div>
                <p class="field-error" x-show="Object.keys(errors).length" x-text="Object.values(errors)[0]?.[0]"></p></form>
            <x-slot:footer><button type="button" class="btn-secondary" @click="edit = false">Cancel</button><button form="pe-f" class="btn-primary" :disabled="busy">Save</button></x-slot:footer>
        </x-modal>
    </div></template>
</div>
</x-layouts.app>
