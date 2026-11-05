<x-layouts.app title="Check-Out" :crumbs="['Gate operations' => route('ops.sessions')]">
<div x-data="checkOut()">
    <x-page-header title="Check-Out" subtitle="Identify the vehicle, verify the plate, settle the server bill. The gate opens only after the server confirms settlement.">
        <button type="button" class="btn-secondary no-print" x-show="step !== 'capture'" @click="resetFlow()">Next vehicle</button>
    </x-page-header>
    <div class="no-print">@include('pages.ops._steps', ['steps' => ['capture' => 'Capture', 'review' => 'Verify plate', 'details' => 'Bill & payment', 'done' => 'Result']])</div>

    @include('pages.ops._anpr-capture', ['dir' => 'exit'])

    {{-- Internal QR ticket fallback --}}
    <section x-show="step === 'capture' && mode === 'ticket'" x-cloak class="card card-pad mt-4" aria-labelledby="tk-h">
        <h2 id="tk-h" class="card-title">Scan internal ticket</h2>
        <p class="mt-1 text-sm text-ink-muted">Backup only — for example when the plate cannot be read. Focus the box and scan the QR code; the scanner types it in.</p>
        <form class="mt-3 flex flex-col gap-2 sm:flex-row" @submit.prevent="ticketPreview()">
            <label class="sr-only" for="tk-qr">Ticket code</label><input id="tk-qr" class="input font-mono" x-model="ticket.qr" placeholder="SPT1-…" autocomplete="off" maxlength="100">
            <button class="btn-primary" :disabled="busy || !ticket.qr.trim()">Look up ticket</button>
        </form>
        <template x-if="ticketQuote">
            <div class="mt-4 grid gap-4 lg:grid-cols-2">
                <div x-data="{ get b() { return ticketQuote } }">@include('pages.partials._bill')</div>
                <div class="space-y-3">
                    <template x-if="ticketQuote.requires_exit_inspection"><div class="alert-warning">An exit safety inspection is required first. Open the session from Parked vehicles to record it.</div></template>
                    @include('pages.ops._payment-fields', ['balanceExpr' => 'ticketQuote.balance_minor', 'overpaidExpr' => 'ticketQuote.overpaid_minor'])
                    <button type="button" class="btn-primary btn-lg w-full" @click="ticketSettle()" :disabled="busy || ticketQuote.requires_exit_inspection">Settle</button>
                </div>
            </div>
        </template>
    </section>

    @include('pages.ops._anpr-review')

    {{-- Step 3: match, bill, inspection, payment --}}
    <section x-show="step === 'details'" x-cloak>
        <template x-if="!previewData && !previewError"><div class="card card-pad"><div class="skeleton h-6 w-1/3"></div><div class="skeleton mt-3 h-24"></div></div></template>
        <template x-if="previewError"><div class="alert-danger" role="alert"><p x-text="previewError.message"></p><div class="mt-2 flex gap-2"><button class="btn-secondary btn-sm" @click="loadPreview()">Try again</button><button class="btn-secondary btn-sm" @click="resetFlow()">Start over</button></div></div></template>

        <div x-show="previewData" class="grid gap-4 lg:grid-cols-3">
            <div class="space-y-4 lg:col-span-2">
                <div class="card card-pad">
                    <h2 class="card-title">Session match</h2>
                    <p class="mt-2 flex flex-wrap items-center gap-2"><span class="plate text-lg" x-text="plateForConfirm || 'No plate'"></span><x-state kind="verification" value="event?.verification_status"/></p>

                    <template x-if="previewData?.session">
                        <dl class="dl mt-4">
                            <dt>Session</dt><dd><a :href="`/ops/sessions/${previewData.session.uuid}`" x-text="previewData.session.reference"></a> <x-state kind="session" value="previewData.session.status" class="ml-1"/></dd>
                            <dt>Checked in</dt><dd x-text="$fmt.dateTime(previewData.session.entry_at)"></dd>
                            <dt>Zone / slot</dt><dd x-text="`${previewData.session.zone} / ${previewData.session.slot}`"></dd>
                            <dt>Package</dt><dd x-text="previewData.session.package"></dd>
                            <dt>Time</dt><dd><x-state kind="time" value="previewData.session.time?.status"/> <span class="text-xs text-danger-ink" x-show="previewData.session.time?.overdue_minutes" x-text="`${$fmt.minutes(previewData.session.time?.overdue_minutes)} over`"></span></dd>
                        </dl>
                    </template>

                    <template x-if="!previewData?.session">
                        <div class="mt-3 space-y-3">
                            <div class="alert-warning" role="alert" x-text="{
                                unknown_vehicle: 'No registered vehicle has exactly this plate. Nothing is matched automatically.',
                                no_active_session: 'This vehicle has no open parking session.',
                                no_plate: 'This reading has no plate. Pick the session below (manual, no-plate exits only).',
                                manual_session: 'Session selected manually.' }[previewData?.match?.result] || 'No session matched.'"></div>
                            <template x-if="previewData?.match?.suggestions?.length"><p class="text-sm">Similar registered plates (province prefix differs): <template x-for="s in previewData.match.suggestions" :key="s.identifier"><strong class="ml-1 font-mono" x-text="s.identifier"></strong></template>. If one is this vehicle, correct the reading.</p></template>
                            <div class="flex flex-wrap gap-2"><button type="button" class="btn-secondary" @click="step = 'review'; verify.action = 'correct'; verify.reason = 'incorrect_result'">Correct the plate</button></div>
                            <form class="flex flex-col gap-2 sm:flex-row" @submit.prevent="findSessions()" x-show="previewData?.match?.result === 'no_plate'">
                                <label class="sr-only" for="ss-q">Search parked vehicles</label><input id="ss-q" class="input" x-model="sessionSearch" placeholder="Session reference or plate">
                                <button class="btn-secondary">Search parked vehicles</button></form>
                            <ul class="divide-y divide-line" x-show="sessionResults.length">
                                <template x-for="s in sessionResults" :key="s.uuid"><li class="flex items-center justify-between gap-2 py-2 text-sm"><span><strong x-text="s.reference"></strong> · <span x-text="s.vehicle?.identifier"></span> · <span x-text="$fmt.dateTime(s.entry_at)"></span></span>
                                    <button type="button" class="btn-secondary btn-sm" @click="chooseSession(s)">Select</button></li></template></ul>
                        </div>
                    </template>
                </div>

                <div class="card card-pad" x-show="previewData?.bill">
                    <h2 class="card-title">Bill</h2>
                    <div class="mt-3" x-data="{ get b() { return bill } }">@include('pages.partials._bill')</div>
                </div>
            </div>

            <aside class="space-y-4 lg:sticky lg:top-20 lg:self-start" x-show="previewData?.session">
                <div class="card card-pad" x-show="needsInspection">
                    <h2 class="card-title">Exit safety inspection required</h2>
                    <p class="mt-1 text-sm text-ink-muted">This session has safety parking. Inspect the vehicle before settlement; the server blocks Check-Out until it is recorded.</p>
                    <form class="mt-3 space-y-3" @submit.prevent="recordInspection()">
                        <div><label class="label" for="ins-c">Condition</label><select id="ins-c" class="input" x-model="inspection.condition"><option value="good">Good</option><option value="minor_damage">Minor damage</option><option value="major_damage">Major damage</option></select></div>
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="check" x-model="inspection.new_damage_found"> New damage found since entry</label>
                        <div><label class="label" for="ins-n">Notes</label><textarea id="ins-n" class="input min-h-16" x-model="inspection.notes" maxlength="2000"></textarea></div>
                        <button class="btn-primary w-full" :disabled="busy">Record exit inspection</button>
                    </form>
                </div>

                <div class="card card-pad" :class="needsInspection && 'opacity-60'">
                    <h2 class="card-title">Payment</h2>
                    <p class="mt-1 text-3xl font-semibold tabular-nums" x-text="$fmt.money(balance)"></p><p class="text-sm text-ink-muted">Balance due (server)</p>
                    <div class="mt-4 space-y-3">@include('pages.ops._payment-fields', ['balanceExpr' => 'balance', 'overpaidExpr' => 'bill?.overpaid_minor'])</div>
                    <button type="button" class="btn-primary btn-lg mt-4 w-full" @click="settle()" :disabled="!canSettle"><span x-show="!busy">Settle & authorise exit</span><span x-show="busy" x-cloak>Settling…</span></button>
                    <p class="mt-2 text-xs text-ink-muted">Attempt key <span class="font-mono" x-text="attemptKey?.slice(0, 8)"></span> — retrying after a network error never charges twice.</p>
                </div>
            </aside>
        </div>
    </section>

    {{-- Step 4: result --}}
    <section x-show="step === 'done' && result" x-cloak class="grid gap-4 lg:grid-cols-5" aria-live="polite">
        <div class="space-y-4 lg:col-span-2 no-print">
            <div class="card card-pad">
                <template x-if="result?.status === 'completed'"><div><p class="chip-success">Settled</p><h2 class="mt-2 text-xl font-semibold">Exit authorised by the server</h2></div></template>
                <template x-if="result?.status === 'payment_pending'"><div><p class="chip-warning">Payment Pending</p><h2 class="mt-2 text-xl font-semibold">Waiting for online payment</h2>
                    <p class="mt-1 text-sm text-ink-muted">The exit is <strong>not</strong> authorised and the gate stays closed until the payment is confirmed.</p></div></template>
                <template x-if="result?.replayed"><p class="mt-2 text-xs text-ink-muted">This was a repeat of an earlier attempt — no second charge was made.</p></template>
                <dl class="dl mt-4">
                    <dt>Session</dt><dd><a :href="`/ops/sessions/${result?.session?.uuid}`" x-text="result?.session?.reference"></a></dd>
                    <dt>Status</dt><dd><x-state kind="session" value="result?.session?.status"/></dd>
                    <dt>Payment</dt><dd><template x-if="result?.payment"><span><x-state kind="payment" value="result.payment.status"/> <span x-text="`${$fmt.money(result.payment.amount_minor)} · ${$fmt.titleCase(result.payment.method)}`"></span></span></template><span x-show="!result?.payment">No payment needed</span></dd>
                    <dt x-show="result?.payment?.change_minor">Change given</dt><dd x-show="result?.payment?.change_minor" class="text-lg" x-text="$fmt.money(result?.payment?.change_minor)"></dd>
                    <dt>Gate</dt><dd><span x-show="gateLabel" :class="gateLabel?.[1]" class="chip" x-text="gateLabel?.[0]"></span><span x-show="!gateLabel" class="text-ink-muted">Not requested</span>
                        <span class="mt-1 block text-xs font-normal text-ink-muted" x-text="gateLabel?.[2] || ''"></span></dd>
                </dl>
                <div class="mt-4 flex flex-wrap gap-2">
                    <button type="button" class="btn-secondary" x-show="result?.gate?.status === 'failed'" @click="retryGate()" :disabled="busy">Retry gate</button>
                    <button type="button" class="btn-secondary" x-show="result?.session?.status === 'exit_authorized'" @click="confirmLeft()" :disabled="busy">Confirm vehicle has left</button>
                    <button type="button" class="btn-primary" x-show="result?.status === 'payment_pending'" @click="resolvePending(true)" :disabled="busy">Payment received</button>
                    <button type="button" class="btn-danger" x-show="result?.status === 'payment_pending'" @click="resolvePending(false)" :disabled="busy">Payment failed</button>
                </div>
                <p class="mt-3 text-xs text-ink-muted" x-show="result?.session?.status === 'exit_authorized'">Gate release mode: the slot is released when you confirm the vehicle has physically left.</p>
            </div>
            <button type="button" class="btn-primary w-full" @click="resetFlow()"><x-icon name="arrow-out" class="h-4 w-4"/>Next vehicle</button>
        </div>
        <div class="lg:col-span-3" x-show="result?.receipt" x-data="{ get rc() { return result?.receipt } }">
            @include('pages.partials._receipt')
            <button type="button" class="btn-secondary mt-3 no-print" @click="printReceipt()"><x-icon name="printer" class="h-4 w-4"/>Print receipt</button>
        </div>
    </section>
</div>
</x-layouts.app>
