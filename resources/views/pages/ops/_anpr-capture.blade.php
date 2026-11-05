{{-- Step 1: capture source. Shared by Check-In and Check-Out ($dir = entry|exit). --}}
<section x-show="step === 'capture'" class="card" aria-labelledby="cap-h">
    <div class="border-b border-line px-4 pt-4 sm:px-5">
        <h2 id="cap-h" class="card-title">Identify the vehicle</h2>
        <p class="mt-1 text-sm text-ink-muted">No live video is shown: real cameras send readings through the camera agent. Use a test photo or type the plate if no reading arrives.</p>
        <div class="mt-4 flex gap-1 overflow-x-auto" role="tablist" aria-label="How to identify the vehicle">
            @foreach (['upload' => 'Upload / Test capture', 'camera' => 'Camera readings', 'manual' => 'Type plate manually'] + ($dir === 'exit' ? ['ticket' => 'Scan internal ticket'] : []) as $m => $label)
                <button type="button" role="tab" class="whitespace-nowrap border-b-2 px-3 py-2 text-sm font-semibold" :aria-selected="(mode === '{{ $m }}').toString()"
                        :class="mode === '{{ $m }}' ? 'border-brand-strong text-brand-stronger' : 'border-transparent text-ink-muted hover:text-ink'" @click="mode = '{{ $m }}'">{{ $label }}
                    @if ($m === 'camera')<span class="chip-brand ml-1" x-show="readings.length" x-text="readings.length"></span>@endif</button>
            @endforeach
        </div>
    </div>

    {{-- Upload / Test capture --}}
    <div x-show="mode === 'upload'" role="tabpanel" class="grid gap-5 p-4 sm:p-5 md:grid-cols-2">
        <div class="space-y-4">
            <div><label class="label" for="cap-cam">Camera / lane</label>
                <select id="cap-cam" class="input" x-model="cameraCode">
                    <option value="">Booth upload (no camera)</option>
                    <template x-for="c in cameras" :key="c.code"><option :value="c.code" x-text="`${c.code} — ${c.name}`"></option></template>
                </select>
                <p class="hint">Only enabled {{ $dir === 'entry' ? 'entry' : 'exit' }} or dual cameras are listed.</p></div>
            <div><label class="label" for="cap-file">Plate photo</label>
                <input id="cap-file" type="file" accept=".jpg,.jpeg,.png,image/jpeg,image/png" capture="environment" class="input file:mr-3 file:rounded-md file:border-0 file:bg-brand-faint file:px-3 file:py-1.5 file:font-semibold file:text-brand-stronger" @change="pickFile($event)" :aria-invalid="uploadError ? 'true' : 'false'" aria-describedby="cap-file-hint">
                <p id="cap-file-hint" class="hint">JPG or PNG, up to 4 MB. On a phone or tablet this opens the camera.</p>
                <p class="field-error" x-show="uploadError" x-text="uploadError" role="alert"></p></div>
            <div x-show="busy" class="space-y-1" aria-live="polite"><div class="h-2 overflow-hidden rounded-full bg-neutral"><div class="h-full bg-brand-strong transition-all" :style="`width:${progress}%`"></div></div>
                <p class="text-xs text-ink-muted" x-text="progress < 100 ? `Uploading… ${progress}%` : 'Recognising the plate on this PC…'"></p></div>
            <button type="button" class="btn-primary btn-lg w-full sm:w-auto" @click="upload()" :disabled="!file || busy"><x-icon name="upload" class="h-5 w-5"/>Recognise plate</button>
        </div>
        <div class="grid min-h-48 place-items-center rounded-lg border border-dashed border-line-strong bg-canvas p-2">
            <template x-if="preview"><img :src="preview" alt="Selected photo preview" class="max-h-72 rounded object-contain"></template>
            <p x-show="!preview" class="text-sm text-ink-muted">Photo preview</p>
        </div>
    </div>

    {{-- Camera readings (pushed by the camera agent) --}}
    <div x-show="mode === 'camera'" x-cloak role="tabpanel" class="p-4 sm:p-5">
        <template x-if="!cameras.length"><div class="alert-info">No {{ $dir === 'entry' ? 'entry' : 'exit' }} camera is registered or enabled. Use Upload / Test capture or type the plate.</div></template>
        <p class="text-sm text-ink-muted" x-show="cameras.length">Unused {{ $dir === 'entry' ? 'entry' : 'exit' }} readings, newest first. Updates every 5 seconds.</p>
        <ul class="mt-3 divide-y divide-line rounded-lg border border-line">
            <template x-for="e in readings" :key="e.uuid">
                <li class="flex flex-wrap items-center justify-between gap-3 px-3 py-3">
                    <div class="flex flex-wrap items-center gap-2"><span class="plate" x-text="e.verified_plate || e.raw_text || 'No plate read'"></span>
                        <x-state kind="recognition" value="e.recognition_status"/><x-state kind="verification" value="e.verification_status"/>
                        <span class="text-xs text-ink-muted" x-text="`${e.camera?.code ?? ''} · ${$fmt.time(e.captured_at)}`"></span></div>
                    <button type="button" class="btn-secondary btn-sm" @click="openEvent(e.uuid)" :disabled="!!e.duplicate_of" x-text="e.duplicate_of ? 'Duplicate' : 'Use this reading'"></button>
                </li>
            </template>
            <li x-show="readingsLoaded && !readings.length" class="px-3 py-6 text-center text-sm text-ink-muted">No unused readings. Readings appear here when a camera agent is running.</li>
        </ul>
    </div>

    {{-- Manual identification --}}
    <form x-show="mode === 'manual'" x-cloak role="tabpanel" class="grid gap-4 p-4 sm:p-5 md:grid-cols-2" @submit.prevent="manualIdentify()">
        <div><label class="label" for="man-plate">Plate number</label>
            <input id="man-plate" class="input-plate" x-model="manual.plate_number" :disabled="manual.reason === 'no_plate'" placeholder="WP CAB-1234" autocomplete="off" :aria-invalid="manualErrors.plate_number ? 'true' : 'false'">
            <p class="field-error" x-show="manualErrors.plate_number" x-text="manualErrors.plate_number?.[0]"></p>
            <p class="hint">Type it exactly as on the plate. The server normalises the format.</p></div>
        <div><label class="label" for="man-reason">Reason <span class="text-danger-ink" aria-hidden="true">*</span></label>
            <select id="man-reason" class="input" x-model="manual.reason" required>
                <template x-for="[v, l] in reasons" :key="v"><option :value="v" x-text="l"></option></template>
            </select><p class="hint">Recorded in the audit trail with your name.</p></div>
        <div class="md:col-span-2"><label class="label" for="man-notes">Notes</label><input id="man-notes" class="input" x-model="manual.notes" maxlength="500"></div>
        <div class="md:col-span-2"><button class="btn-primary" :disabled="busy || (manual.reason !== 'no_plate' && !manual.plate_number.trim())">Continue with this plate</button></div>
    </form>
</section>
