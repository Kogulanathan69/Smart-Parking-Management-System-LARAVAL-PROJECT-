<x-layouts.app title="Check-In" :crumbs="['Gate operations' => route('ops.sessions')]">
<div x-data="checkIn()">
    <x-page-header title="Check-In" subtitle="Identify the vehicle, verify the plate, choose zone and package, then confirm. The server assigns the slot.">
        <button type="button" class="btn-secondary" x-show="step !== 'capture'" @click="resetFlow()">New vehicle</button>
    </x-page-header>
    @include('pages.ops._steps', ['steps' => ['capture' => 'Capture', 'review' => 'Verify plate', 'details' => 'Parking details', 'done' => 'Checked in']])

    @include('pages.ops._anpr-capture', ['dir' => 'entry'])
    @include('pages.ops._anpr-review')

    {{-- Step 3: parking details --}}
    <section x-show="step === 'details'" x-cloak aria-labelledby="det-h">
        <template x-if="!previewData && !previewError"><div class="card card-pad"><div class="skeleton h-6 w-1/3"></div><div class="skeleton mt-3 h-24"></div></div></template>
        <template x-if="previewError"><div class="alert-danger" role="alert"><p x-text="previewError.message"></p>
            <div class="mt-2 flex gap-2"><button class="btn-secondary btn-sm" @click="loadPreview()">Try again</button><button class="btn-secondary btn-sm" @click="resetFlow()">Start over</button></div></div></template>

        <div x-show="previewData" class="grid gap-4 lg:grid-cols-3">
            <div class="space-y-4 lg:col-span-2">
                <div class="card card-pad">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div><h2 id="det-h" class="card-title">Vehicle</h2>
                            <p class="mt-2 flex flex-wrap items-center gap-2"><span class="plate text-lg" x-text="plateForConfirm"></span><x-state kind="verification" value="event?.verification_status"/>
                                <span :class="previewData?.is_new_vehicle ? 'chip-info' : 'chip-success'" x-text="previewData?.is_new_vehicle ? 'New vehicle — will be registered' : 'Registered vehicle'"></span></p></div>
                    </div>
                    <template x-if="previewData?.blocked"><div class="alert-danger mt-3" role="alert">This vehicle is already parked (session <strong x-text="previewData.blocked.session_reference"></strong>). It must be checked out before a new Check-In.
                        <a class="font-semibold" :href="`{{ route('ops.sessions') }}?plate=${encodeURIComponent(plateForConfirm)}`">Find the session</a></div></template>
                    <template x-if="previewData?.suggestions?.length"><div class="alert-info mt-3">A registered vehicle differs only by province prefix:
                        <template x-for="s in previewData.suggestions" :key="s.identifier"><strong class="ml-1 font-mono" x-text="s.identifier"></strong></template>.
                        It is <em>not</em> matched automatically. If it is the same vehicle, start over and correct the plate.</div></template>
                    <template x-if="previewData?.active_reservation"><div class="mt-3" :class="previewData.active_reservation.is_valid ? 'alert-success' : 'alert-warning'">
                        <span x-text="previewData.active_reservation.is_valid ? `Guaranteed parking: slot ${previewData.active_reservation.slot} in zone ${previewData.active_reservation.zone} (${previewData.active_reservation.package}) until ${$fmt.dateTime(previewData.active_reservation.expires_at)}. Zone and package are pre-selected.` : 'This vehicle had a reservation that has expired. A new package will be charged.'"></span></div></template>
                </div>

                <div class="card card-pad" x-show="!previewData?.blocked">
                    <h2 class="card-title">Parking details</h2>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div><label class="label" for="e-cat">Vehicle category <span class="text-danger-ink" aria-hidden="true">*</span></label>
                            <select id="e-cat" class="input" x-model="entry.vehicle_category_id" required :disabled="!!previewData?.vehicle" :aria-invalid="entryErrors.vehicle_category_id ? 'true' : 'false'">
                                <option value="">Choose…</option><template x-for="c in (ref?.vehicle_categories || [])" :key="c.id"><option :value="c.id" x-text="c.name"></option></template></select>
                            <p class="hint" x-show="previewData?.vehicle">From the vehicle register. An admin can change it.</p>
                            <p class="field-error" x-show="entryErrors.vehicle_category_id" x-text="entryErrors.vehicle_category_id?.[0]"></p></div>
                        <div><label class="label" for="e-fuel">Fuel type <span class="text-danger-ink" aria-hidden="true" x-show="fuelOptions.length > 1">*</span></label>
                            <select id="e-fuel" class="input" x-model="entry.fuel_type" :disabled="!entry.vehicle_category_id" :aria-invalid="entryErrors.fuel_type ? 'true' : 'false'">
                                <option value="">Choose…</option><template x-for="f in fuelOptions" :key="f.value"><option :value="f.value" x-text="f.label"></option></template></select>
                            <p class="hint" x-show="previewData?.vehicle">Changing it corrects the register (audited).</p>
                            <p class="field-error" x-show="entryErrors.fuel_type" x-text="entryErrors.fuel_type?.[0]"></p></div>
                    </div>

                    <fieldset class="mt-5" :disabled="!entry.vehicle_category_id"><legend class="label">Zone <span class="text-danger-ink" aria-hidden="true">*</span></legend>
                        <p class="hint mb-2" x-show="!entry.vehicle_category_id">Choose the vehicle category first to see free slots.</p>
                        <div class="grid gap-2 sm:grid-cols-2">
                            <template x-for="z in zones" :key="z.zone_id">
                                <label class="flex cursor-pointer items-center justify-between gap-2 rounded-lg border p-3" :class="[String(entry.parking_zone_id) === String(z.zone_id) ? 'border-brand-strong bg-brand-faint' : 'border-line', (!z.allowed || z.available === 0) && entry.vehicle_category_id ? 'opacity-60' : '']">
                                    <span class="flex items-center gap-2"><input type="radio" name="zone" :value="z.zone_id" x-model="entry.parking_zone_id" :disabled="entry.vehicle_category_id && (!z.allowed || z.available === 0)">
                                        <span><span class="block font-semibold" x-text="`Zone ${z.code}`"></span><span class="block text-xs text-ink-muted" x-text="z.name"></span></span></span>
                                    <span class="text-right text-xs" x-show="entry.vehicle_category_id"><span x-show="!z.allowed" class="chip-neutral">Not for this vehicle</span>
                                        <span x-show="z.allowed" :class="z.available > 0 ? 'chip-success' : 'chip-danger'" x-text="z.available > 0 ? `${z.available} free` : 'Full'"></span></span>
                                </label>
                            </template>
                        </div>
                        <p class="field-error" x-show="entryErrors.parking_zone_id" x-text="entryErrors.parking_zone_id?.[0]"></p></fieldset>

                    <div class="mt-5 grid gap-4 sm:grid-cols-2">
                        <div><label class="label" for="e-pkg">Package <span class="text-danger-ink" aria-hidden="true">*</span></label>
                            <select id="e-pkg" class="input" x-model="entry.parking_package_id" :disabled="!entry.parking_zone_id" :aria-invalid="entryErrors.parking_package_id ? 'true' : 'false'">
                                <option value="">Choose…</option><template x-for="p in packagesForZone" :key="p.id"><option :value="p.id" x-text="p.name + (p.guarantees_slot ? ' — guaranteed slot' : '')"></option></template></select>
                            <p class="hint">Prices come from the published rate card; the server calculates the bill.</p>
                            <p class="field-error" x-show="entryErrors.parking_package_id" x-text="entryErrors.parking_package_id?.[0]"></p></div>
                        <div><span class="label">Slot</span><p class="input flex items-center bg-neutral text-ink-muted">Assigned automatically by the server</p></div>
                    </div>

                    <details class="mt-5 rounded-lg border border-line p-3"><summary class="cursor-pointer text-sm font-semibold">Customer (optional)</summary>
                        <div class="mt-3 grid gap-4 sm:grid-cols-2">
                            <div><label class="label" for="e-cn">Customer name</label><input id="e-cn" class="input" x-model="entry.customer_name" maxlength="100"></div>
                            <div><label class="label" for="e-cp">Mobile number</label><input id="e-cp" class="input" type="tel" x-model="entry.customer_phone" placeholder="077 123 4567" :aria-invalid="entryErrors.customer_phone ? 'true' : 'false'">
                                <p class="hint">If it matches a registered customer, the session is linked to their account.</p><p class="field-error" x-show="entryErrors.customer_phone" x-text="entryErrors.customer_phone?.[0]"></p></div>
                            <div class="sm:col-span-2"><label class="label" for="e-notes">Notes</label><input id="e-notes" class="input" x-model="entry.notes" maxlength="500"></div>
                        </div></details>
                </div>
            </div>

            <aside class="lg:sticky lg:top-20 lg:self-start" x-show="!previewData?.blocked">
                <div class="card card-pad">
                    <h2 class="card-title">Final review</h2>
                    <dl class="dl mt-3">
                        <dt>Plate</dt><dd><span class="plate" x-text="plateForConfirm"></span></dd>
                        <dt>Category</dt><dd x-text="category?.name || '—'"></dd>
                        <dt>Fuel</dt><dd x-text="fuelOptions.find(f => f.value === entry.fuel_type)?.label || '—'"></dd>
                        <dt>Zone</dt><dd x-text="selectedZone ? `Zone ${selectedZone.code}` : '—'"></dd>
                        <dt>Package</dt><dd x-text="selectedPackage?.name || '—'"></dd>
                        <dt>Entry gate</dt><dd class="font-normal text-ink-muted">Not controlled by ParkFlow</dd>
                    </dl>
                    <label class="mt-4 flex items-start gap-2 text-sm"><input type="checkbox" class="check mt-0.5" x-model="confirmed"><span>I have checked that the plate shown matches the vehicle at the gate.</span></label>
                    <button type="button" class="btn-primary btn-lg mt-4 w-full" @click="confirmEntry()" :disabled="busy || !readyToConfirm">
                        <span x-show="!busy">Confirm Check-In</span><span x-show="busy" x-cloak>Recording…</span></button>
                    <p class="mt-2 text-xs text-ink-muted" x-show="!readyToConfirm && !busy">Complete the required fields and tick the check box.</p>
                </div>
            </aside>
        </div>
    </section>

    {{-- Step 4: done --}}
    <section x-show="step === 'done' && result" x-cloak class="card card-pad" aria-live="polite">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div><p class="chip-success">Checked in</p>
                <h2 class="mt-2 text-xl font-semibold" x-text="result?.data?.reference"></h2>
                <p class="mt-1 text-sm text-ink-muted">Recorded by the server at <span x-text="$fmt.dateTime(result?.data?.entry_at)"></span>.</p></div>
            <span class="plate text-2xl" x-text="result?.data?.vehicle?.plate_number || result?.data?.vehicle?.identifier"></span>
        </div>
        <dl class="dl mt-5 sm:grid-cols-[10rem_1fr_10rem_1fr]">
            <dt>Zone</dt><dd x-text="result?.data?.zone?.name"></dd>
            <dt>Slot</dt><dd class="text-lg" x-text="result?.data?.slot?.code"></dd>
            <dt>Package</dt><dd x-text="result?.data?.package?.name"></dd>
            <dt>Package ends</dt><dd x-text="result?.data?.package_ends_at ? $fmt.dateTime(result.data.package_ends_at) : 'No fixed end (hourly)'"></dd>
            <dt>Entry gate</dt><dd class="font-normal" x-text="result?.gate?.note || 'Not requested'"></dd>
        </dl>
        <div class="mt-5 flex flex-wrap gap-2">
            <button type="button" class="btn-primary" @click="resetFlow()"><x-icon name="arrow-in" class="h-4 w-4"/>Next vehicle</button>
            <a class="btn-secondary" :href="`/ops/sessions/${result?.data?.uuid}`">Open session</a>
            <button type="button" class="btn-secondary" @click="printTicket()"><x-icon name="printer" class="h-4 w-4"/>Print internal ticket</button>
        </div>
        <p class="mt-3 text-xs text-ink-muted">The printed ticket is optional: Check-Out works from the number plate. The QR on it is a backup for staff.</p>
    </section>
</div>
</x-layouts.app>
