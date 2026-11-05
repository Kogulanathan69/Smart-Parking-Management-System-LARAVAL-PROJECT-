<x-layouts.app title="Plate reading" :crumbs="['ANPR review queue' => route('ops.anpr.queue')]">
<div x-data="anprEventDetail(@js($uuid))">
    @include('pages.ops._record-states', ['var' => 'e'])
    <template x-if="e"><div>
        <x-page-header title="Plate reading">
            <a class="btn-primary" :href="flowUrl" x-show="!e.consumed_at && !e.duplicate_of && e.verification_status !== 'rejected'" x-text="e.direction === 'entry' ? 'Continue Check-In' : 'Continue Check-Out'"></a>
        </x-page-header>
        <div class="grid gap-4 lg:grid-cols-2">
            <section class="card card-pad">
                <div class="grid min-h-40 place-items-center rounded-lg border border-line bg-canvas p-2">
                    <template x-for="img in (e.images || []).filter(i => i.url)" :key="img.uuid"><figure class="text-center"><img :src="img.url" :alt="`${img.category} image`" class="max-h-56 rounded object-contain"><figcaption class="mt-1 text-xs text-ink-muted" x-text="$fmt.titleCase(img.category)"></figcaption></figure></template>
                    <p x-show="!(e.images || []).some(i => i.url)" class="text-sm text-ink-muted" x-text="(e.images || []).length ? 'Image deleted by the retention policy.' : 'No image stored.'"></p>
                </div>
                <p class="mt-2 text-xs text-ink-muted">Every view of these images is recorded in the audit trail.</p>
            </section>
            <section class="card card-pad"><dl class="dl">
                <dt>Read as</dt><dd><span class="plate" x-text="e.verified_plate || e.raw_text || '—'"></span></dd>
                <dt>Normalised</dt><dd class="font-mono" x-text="e.normalized_plate || '—'"></dd><dt>Format</dt><dd x-text="$fmt.titleCase(e.plate_format)"></dd>
                <dt>Recognition</dt><dd><x-state kind="recognition" value="e.recognition_status"/></dd><dt>Verification</dt><dd><x-state kind="verification" value="e.verification_status"/></dd>
                <dt>OCR confidence</dt><dd x-text="$fmt.percent(e.confidence_percent)"></dd>
                <dt>Detector confidence</dt><dd x-text="e.candidates?.[0]?.detector_confidence_bp != null ? $fmt.percent(e.candidates[0].detector_confidence_bp / 100) : 'Not reported'"></dd>
                <dt>Direction</dt><dd x-text="e.direction === 'entry' ? 'Check-In' : 'Check-Out'"></dd><dt>Source</dt><dd x-text="`${$fmt.titleCase(e.source)}${e.camera ? ' · ' + e.camera.code + ' ' + e.camera.name : ''}`"></dd>
                <dt>Captured</dt><dd x-text="$fmt.dateTime(e.captured_at)"></dd><dt>Processing</dt><dd x-text="e.processing_ms != null ? `${e.processing_ms} ms` : '—'"></dd>
                <dt>Used for session</dt><dd x-text="e.consumed_at ? `Yes, ${$fmt.dateTime(e.consumed_at)}` : 'No'"></dd>
                <dt x-show="e.error_code">Error</dt><dd x-show="e.error_code" class="font-mono" x-text="e.error_code"></dd>
                <dt x-show="e.duplicate_of">Duplicate of</dt><dd x-show="e.duplicate_of"><a :href="`/ops/anpr/${e.duplicate_of}`">Original reading</a></dd>
            </dl></section>
            <template x-if="(e.review_suggestions || []).length > 0">
                <section class="card card-pad lg:col-span-2">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="card-title">AI Review Suggestions</h2>
                        <span class="chip-neutral">Staff review required</span>
                    </div>
                    <p class="mt-2 text-sm text-ink-muted">
                        Experimental region OCR suggestions. These are not verified plate readings.
                        Staff must compare them with the actual vehicle before making a decision.
                    </p>
                    <ul class="mt-3 flex flex-wrap gap-3">
                        <template x-for="(suggestion, index) in e.review_suggestions" :key="index">
                            <li class="rounded-lg border border-line p-3">
                                <span class="plate" x-text="suggestion.text"></span>
                                <span class="mt-1 block text-xs text-ink-muted">
                                    Requires staff verification
                                </span>
                            </li>
                        </template>
                    </ul>
                </section>
            </template>
            <template x-if="e.partial_review_clues && ((e.partial_review_clues.province_clues || []).length || (e.partial_review_clues.registration_clues || []).length)">
                <section class="card card-pad lg:col-span-2">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="card-title">Unverified AI Clues</h2>
                        <span class="chip-neutral">Staff review required</span>
                    </div>
                    <p class="mt-2 text-sm text-ink-muted">
                        Experimental OCR observations only. Province and registration clues may come from different rotations.
                        They must not be combined or treated as a verified plate without checking the actual vehicle.
                    </p>
                    <div class="mt-3 grid gap-4 md:grid-cols-2">
                        <div>
                            <h3 class="text-sm font-semibold">Province clues</h3>
                            <ul class="mt-2 space-y-2">
                                <template x-for="(clue, index) in (e.partial_review_clues.province_clues || [])" :key="index">
                                    <li class="rounded-lg border border-line p-3">
                                        <span class="plate" x-text="clue.text"></span>
                                        <span class="mt-1 block text-xs text-ink-muted"
                                              x-text="`OCR score: ${Math.round(clue.confidence * 100)}% · Rotation: ${clue.angle}°`"></span>
                                    </li>
                                </template>
                                <li x-show="!(e.partial_review_clues.province_clues || []).length" class="text-sm text-ink-muted">
                                    No province clues.
                                </li>
                            </ul>
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold">Registration clues</h3>
                            <ul class="mt-2 space-y-2">
                                <template x-for="(clue, index) in (e.partial_review_clues.registration_clues || [])" :key="index">
                                    <li class="rounded-lg border border-line p-3">
                                        <span class="plate" x-text="clue.text"></span>
                                        <span class="mt-1 block text-xs text-ink-muted"
                                              x-text="`OCR score: ${Math.round(clue.confidence * 100)}% · Rotation: ${clue.angle}°`"></span>
                                    </li>
                                </template>
                                <li x-show="!(e.partial_review_clues.registration_clues || []).length" class="text-sm text-ink-muted">
                                    No registration clues.
                                </li>
                            </ul>
                        </div>
                    </div>
                </section>
            </template>
            <section class="card lg:col-span-2"><h2 class="card-title px-4 pt-4">Review trail</h2>
                <div class="table-wrap mt-2"><table class="tbl"><thead><tr><th>When</th><th>Action</th><th>From</th><th>To</th><th>Reason</th><th>By (user ID)</th></tr></thead><tbody>
                    <template x-for="(r, i) in e.reviews || []" :key="i"><tr><td x-text="$fmt.dateTime(r.created_at)"></td><td x-text="$fmt.titleCase(r.action)"></td><td class="font-mono" x-text="r.from_plate || '—'"></td><td class="font-mono" x-text="r.to_plate || '—'"></td><td x-text="$fmt.titleCase(r.reason) || '—'"></td><td x-text="r.user_id"></td></tr></template>
                    <tr x-show="!(e.reviews || []).length"><td colspan="6" class="py-4 text-center text-ink-muted">No decisions yet.</td></tr></tbody></table></div></section>
        </div>
    </div></template>
</div>
</x-layouts.app>
