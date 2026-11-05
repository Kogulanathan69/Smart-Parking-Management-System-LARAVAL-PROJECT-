{{-- Expects `fin` (ReportService::financial) in scope. --}}
<section class="card card-pad" aria-labelledby="fin-h">
    <div class="flex flex-wrap items-baseline justify-between gap-2"><h2 id="fin-h" class="card-title">Revenue, last 7 days</h2><span class="text-xs text-ink-muted" x-text="`${$fmt.date(fin.from)} – ${$fmt.date(fin.to)}`"></span></div>
    <p class="mt-2 text-3xl font-semibold tabular-nums" x-text="$fmt.money(fin.revenue_minor)"></p>
    <p class="text-sm text-ink-muted" x-text="`${fin.payment_count} settled payments`"></p>
    <ul class="mt-4 space-y-2" aria-label="Revenue by day">
        <template x-for="day in fin.by_day" :key="day.date ?? day.day">
            <li class="grid grid-cols-[6.5rem_1fr_auto] items-center gap-3 text-sm">
                <span class="text-ink-muted" x-text="$fmt.date(day.date ?? day.day)"></span>
                <span class="h-2.5 rounded-full bg-neutral"><span class="block h-full rounded-full bg-brand-strong" :style="`width:${maxDay ? Math.max(2, Math.round((day.revenue_minor ?? day.amount_minor ?? 0) / maxDay * 100)) : 0}%`"></span></span>
                <span class="tabular-nums" x-text="$fmt.money(day.revenue_minor ?? day.amount_minor)"></span>
            </li>
        </template>
        <li x-show="!fin.by_day.length" class="text-sm text-ink-muted">No payments in this period.</li>
    </ul>
    <div class="mt-4 grid grid-cols-2 gap-3 border-t border-line pt-4 text-sm sm:grid-cols-4">
        <div><div class="text-ink-muted">Pending payments</div><div class="font-semibold" x-text="fin.pending_payments"></div></div>
        <div><div class="text-ink-muted">Failed payments</div><div class="font-semibold" x-text="fin.failed_payments"></div></div>
        <div><div class="text-ink-muted">Refunds</div><div class="font-semibold" x-text="$fmt.money(fin.refunds_minor)"></div></div>
        <div><div class="text-ink-muted">Penalties applied</div><div class="font-semibold" x-text="$fmt.money(fin.penalties?.applied_minor)"></div></div>
    </div>
</section>
