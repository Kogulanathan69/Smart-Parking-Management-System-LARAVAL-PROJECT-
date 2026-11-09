{{-- Printable receipt. Expects `rc` (ReceiptResource) in scope. --}}
<article class="card card-pad print:border-0" aria-label="Receipt">
    <header class="flex flex-wrap items-start justify-between gap-3 border-b border-line pb-4">
        <div class="flex items-center gap-3"><span class="grid h-11 w-11 place-items-center rounded-full bg-brand-strong text-lg font-black text-white">P</span><div><p class="text-lg font-bold">ParkFlow parking receipt</p><p class="font-mono text-sm text-ink-muted" x-text="`Receipt ${rc?.number}`"></p></div></div>
        <div class="text-right text-sm"><p x-text="$fmt.dateTime(rc?.issued_at)"></p><p class="text-ink-muted">Sri Lanka time</p></div>
    </header>
    <dl class="dl mt-3">
        <dt>Session</dt><dd x-text="rc?.session_reference ?? rc?.breakdown?.bill?.session_reference"></dd>
        <dt>Vehicle</dt><dd x-text="rc?.breakdown?.bill?.plate_number || rc?.vehicle"></dd>
        <dt>Zone · package</dt><dd x-text="`${rc?.breakdown?.bill?.zone ?? '—'} · ${rc?.breakdown?.bill?.package ?? '—'}`"></dd>
        <dt>Check-In</dt><dd x-text="rc?.breakdown?.bill ? $fmt.dateTime(rc.breakdown.bill.entry_at) : '—'"></dd>
        <dt>Check-Out</dt><dd x-text="rc?.breakdown?.bill ? $fmt.dateTime(rc.breakdown.bill.exit_at) : '—'"></dd>
    </dl>
    <div class="mt-4" x-data="{ get b() { return rc?.breakdown?.bill || rc?.breakdown?.quote } }">@include('pages.partials._bill')</div>
    <div class="mt-3 grid gap-1 text-sm">
        <p><span class="text-ink-muted">Paid:</span> <strong x-text="$fmt.money(rc?.paid_minor)"></strong></p>
        <p x-show="rc?.credit_issued_minor > 0"><span class="text-ink-muted">Credit issued to account:</span> <strong x-text="$fmt.money(rc?.credit_issued_minor)"></strong></p>
        <p x-show="rc?.refund_minor > 0"><span class="text-ink-muted">Refund due:</span> <strong x-text="$fmt.money(rc?.refund_minor)"></strong></p>
    </div>
</article>
