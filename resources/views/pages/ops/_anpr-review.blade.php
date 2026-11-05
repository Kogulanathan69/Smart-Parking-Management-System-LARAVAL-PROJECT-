{{-- Step 2: staff verification of a reading. --}}
<section x-show="step === 'review' && event" x-cloak class="grid gap-4 lg:grid-cols-5" aria-labelledby="rev-h">
    <div class="card card-pad lg:col-span-2">
        <h2 id="rev-h" class="card-title">Reading</h2>
        <div class="mt-3 grid min-h-32 place-items-center rounded-lg border border-line bg-canvas p-2">
            <template x-if="crop"><img :src="crop.url" alt="Plate image captured for this reading" class="max-h-48 w-auto rounded object-contain"></template>
            <p x-show="!crop" class="text-sm text-ink-muted">No image stored for this reading.</p>
        </div>
        <dl class="dl mt-4">
            <dt>Read as</dt><dd><span class="plate" x-text="displayPlate || '—'"></span></dd>
            <dt>Raw text</dt><dd class="font-mono" x-text="event?.raw_text || '—'"></dd>
            <dt>Format</dt><dd x-text="$fmt.titleCase(event?.plate_format || 'unknown')"></dd>
            <dt>Recognition</dt><dd><x-state kind="recognition" value="event?.recognition_status"/></dd>
            <dt>Verification</dt><dd><x-state kind="verification" value="event?.verification_status"/></dd>
            <dt>OCR confidence</dt><dd><span x-text="event?.confidence_percent != null ? $fmt.percent(event.confidence_percent) : 'Not available'"></span>
                <span class="block text-xs font-normal text-ink-muted">How sure the text reader is about the characters.</span></dd>
            <dt>Detector confidence</dt><dd><span x-text="topCandidate?.detector_confidence_bp != null ? $fmt.percent(topCandidate.detector_confidence_bp / 100) : 'Not reported'"></span>
                <span class="block text-xs font-normal text-ink-muted">How sure the model is that this is a plate. It does not confirm the characters.</span></dd>
            <dt>Source</dt><dd x-text="`${$fmt.titleCase(event?.source)}${event?.camera ? ' · ' + event.camera.code : ''} · ${$fmt.dateTime(event?.captured_at)}`"></dd>
        </dl>
        <template x-if="topCandidate?.confusion_corrected"><div class="alert-warning mt-3 text-xs">Some characters were adjusted to fit a plate format (for example 0/O, 1/I, 8/B). Check carefully.</div></template>
        <template x-if="(event?.candidates || []).length > 1"><div class="mt-3"><p class="text-xs font-semibold text-ink-muted">Other candidates</p>
            <ul class="mt-1 flex flex-wrap gap-2"><template x-for="c in event.candidates.slice(1)" :key="c.normalized"><li class="chip-neutral" x-text="`${c.display} · ${$fmt.percent(c.confidence_bp / 100)}`"></li></template></ul></div></template>
        <template x-if="(event?.review_suggestions || []).length > 0">
            <div class="mt-4 rounded-lg border border-line bg-canvas p-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 class="text-sm font-semibold">AI Review Suggestions</h3>
                    <span class="chip-neutral">Staff review required</span>
                </div>
                <p class="mt-2 text-xs text-ink-muted">
                    Experimental region OCR suggestions only. Compare with the plate image and actual vehicle. These are not verified readings.
                </p>
                <ul class="mt-3 space-y-2">
                    <template x-for="(suggestion, index) in event.review_suggestions" :key="index">
                        <li class="rounded-lg border border-line p-3">
                            <span class="plate" x-text="suggestion.text"></span>
                            <span class="block mt-1 text-xs text-ink-muted">
                                Requires explicit staff verification.
                            </span>
                        </li>
                    </template>
                </ul>
            </div>
        </template>
        {{-- Adaptive OCR partial clues: display only, never auto-verify. --}}
        <template x-if="event?.partial_review_clues && ((event.partial_review_clues.province_clues || []).length || (event.partial_review_clues.registration_clues || []).length)">
            <div class="mt-4 rounded-lg border border-line bg-canvas p-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 class="text-sm font-semibold">Unverified AI Clues</h3>
                    <span class="chip-neutral">Staff review required</span>
                </div>

                <p class="mt-2 text-xs text-ink-muted">
                    Experimental OCR observations from rotated plate images.
                    These are separate clues, not a confirmed plate number.
                </p>

                <div class="mt-3">
                    <p class="text-xs font-semibold">Province clues</p>
                    <ul class="mt-2 flex flex-wrap gap-2">
                        <template x-for="(clue, index) in (event.partial_review_clues.province_clues || [])" :key="index">
                            <li class="chip-neutral">
                                <span class="font-mono" x-text="clue.text"></span>
                                <span class="text-xs" x-text="`(${Math.round(clue.confidence * 100)}% OCR score)`"></span>
                            </li>
                        </template>
                    </ul>
                    <p x-show="!(event.partial_review_clues.province_clues || []).length" class="mt-1 text-xs text-ink-muted">No province clue found.</p>
                </div>

                <div class="mt-3">
                    <p class="text-xs font-semibold">Registration clues</p>
                    <ul class="mt-2 flex flex-wrap gap-2">
                        <template x-for="(clue, index) in (event.partial_review_clues.registration_clues || [])" :key="index">
                            <li class="chip-neutral">
                                <span class="font-mono" x-text="clue.text"></span>
                                <span class="text-xs" x-text="`(${Math.round(clue.confidence * 100)}% OCR score)`"></span>
                            </li>
                        </template>
                    </ul>
                    <p x-show="!(event.partial_review_clues.registration_clues || []).length" class="mt-1 text-xs text-ink-muted">No registration clue found.</p>
                </div>

                <p class="mt-3 text-xs text-ink-muted">
                    Compare every clue with the original image and actual vehicle.
                    OCR scores are not measured accuracy. Staff must explicitly verify.
                </p>
            </div>
        </template>
        <p class="mt-3 text-xs text-ink-muted">Confidence is a model score for this image, not a measured accuracy.</p>
    </div>

    <div class="card card-pad lg:col-span-3">
        <h2 class="card-title">Your decision</h2>
        <template x-if="event?.duplicate_of"><div class="alert-warning mt-3">This is a duplicate of an earlier reading of the same plate. <a :href="`?event=${event.duplicate_of}`" @click.prevent="openEvent(event.duplicate_of)">Open the original reading</a>.</div></template>
        <template x-if="event?.consumed_at"><div class="alert-info mt-3">This reading was already used. Capture a new one for another vehicle.</div></template>
        <template x-if="event?.recognition_status === 'engine_error'"><div class="alert-danger mt-3">The recognition engine could not process the photo. Type the plate (Correct) or use manual identification.</div></template>
        <template x-if="['low_confidence','unreadable'].includes(event?.recognition_status)"><div class="alert-warning mt-3">This reading needs careful checking before use.</div></template>

        <form class="mt-4 space-y-4" @submit.prevent="submitVerify()" x-show="usable">
            <fieldset><legend class="label">Is the plate correct?</legend>
                <div class="grid gap-2 sm:grid-cols-3">
                    <label class="flex cursor-pointer items-start gap-2 rounded-lg border p-3" :class="verify.action === 'confirm' ? 'border-brand-strong bg-brand-faint' : 'border-line'" x-show="canConfirm">
                        <input type="radio" class="mt-1" value="confirm" x-model="verify.action"><span><span class="block font-semibold">Confirm</span><span class="text-xs text-ink-muted">Matches the vehicle</span></span></label>
                    <label class="flex cursor-pointer items-start gap-2 rounded-lg border p-3" :class="verify.action === 'correct' ? 'border-brand-strong bg-brand-faint' : 'border-line'">
                        <input type="radio" class="mt-1" value="correct" x-model="verify.action"><span><span class="block font-semibold">Correct</span><span class="text-xs text-ink-muted">Type the right plate</span></span></label>
                    <label class="flex cursor-pointer items-start gap-2 rounded-lg border p-3" :class="verify.action === 'reject' ? 'border-danger-ink bg-danger' : 'border-line'">
                        <input type="radio" class="mt-1" value="reject" x-model="verify.action"><span><span class="block font-semibold">Reject</span><span class="text-xs text-ink-muted">Not usable</span></span></label>
                </div></fieldset>
            <div x-show="verify.action === 'correct'"><label class="label" for="v-plate">Correct plate</label>
                <input id="v-plate" class="input-plate" x-model="verify.plate_number" autocomplete="off" :aria-invalid="verifyErrors.plate_number ? 'true' : 'false'">
                <p class="field-error" x-show="verifyErrors.plate_number" x-text="verifyErrors.plate_number?.[0]"></p></div>
            <div x-show="verify.action !== 'confirm'"><label class="label" for="v-reason">Reason <span class="text-danger-ink" aria-hidden="true">*</span></label>
                <select id="v-reason" class="input" x-model="verify.reason" :aria-invalid="verifyErrors.reason ? 'true' : 'false'"><option value="">Choose…</option>
                    <template x-for="[v, l] in reasons" :key="v"><option :value="v" x-text="l"></option></template></select>
                <p class="field-error" x-show="verifyErrors.reason" x-text="verifyErrors.reason?.[0]"></p></div>
            <div><label class="label" for="v-notes">Notes (optional)</label><input id="v-notes" class="input" x-model="verify.notes" maxlength="500"></div>
            <p class="text-xs text-ink-muted">Your decision, name and time are written to the ANPR review trail. Corrections and rejections appear in the auditor's corrections report.</p>
            <div class="flex flex-wrap gap-2">
                <button class="btn-primary" :class="verify.action === 'reject' && 'btn-danger'" :disabled="busy" x-text="{confirm: 'Confirm plate', correct: 'Save correction', reject: 'Reject reading'}[verify.action]"></button>
                <button type="button" class="btn-secondary" @click="resetFlow()">Start over</button>
            </div>
        </form>
        <div class="mt-4 flex flex-wrap gap-2" x-show="!usable"><button type="button" class="btn-secondary" @click="resetFlow()">Start over</button></div>
        <div class="mt-4 border-t border-line pt-4" x-show="usable && ['engine_error','unreadable'].includes(event?.recognition_status)">
            <button type="button" class="btn-ghost" @click="linkOriginal = event.uuid; mode = 'manual'; manual.reason = event.recognition_status === 'engine_error' ? 'engine_unavailable' : 'unreadable'; step = 'capture'">Use manual identification instead</button>
        </div>
    </div>
</section>
