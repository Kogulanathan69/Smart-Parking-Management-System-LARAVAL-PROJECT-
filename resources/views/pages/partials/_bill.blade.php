{{-- Server bill (SessionController::billFor / receipt.breakdown.bill). Expects `b` in scope. Display only. --}}
<div>
    <table class="tbl">
        <thead><tr><th scope="col">Item</th><th scope="col" class="tbl-num">Amount</th></tr></thead>
        <tbody>
            <template x-for="(l, i) in (b?.lines || [])" :key="i">
                <tr><td><span x-text="l.label"></span>
                    <span class="block text-xs text-ink-muted" x-show="l.detail?.minutes" x-text="l.detail?.minutes ? `${$fmt.minutes(l.detail.minutes)} at ${$fmt.money(l.detail.hourly_minor)}/hour (daily cap ${$fmt.money(l.detail.daily_cap_minor)})` : ''"></span>
                    <span class="block text-xs text-ink-muted" x-show="l.detail?.reason" x-text="l.detail?.reason"></span></td>
                    <td class="tbl-num" x-text="$fmt.money(l.amount_minor)"></td></tr>
            </template>
            <tr x-show="b?.tax_minor"><td>Tax</td><td class="tbl-num" x-text="$fmt.money(b?.tax_minor)"></td></tr>
        </tbody>
        <tfoot class="text-sm">
            <tr><th scope="row" class="!bg-surface text-left !text-sm !normal-case !tracking-normal text-ink">Total</th><td class="tbl-num text-base font-semibold" x-text="$fmt.money(b?.total_payable_minor ?? b?.total_minor)"></td></tr>
            <tr><th scope="row" class="!bg-surface text-left !text-sm !normal-case !tracking-normal font-normal text-ink-muted">Already paid</th><td class="tbl-num" x-text="$fmt.money(b?.amount_paid_minor ?? b?.paid_minor ?? 0)"></td></tr>
            <tr><th scope="row" class="!bg-surface text-left !text-sm !normal-case !tracking-normal text-ink">Balance due</th><td class="tbl-num text-lg font-bold text-ink" x-text="$fmt.money(b?.balance_minor)"></td></tr>
            <tr x-show="b?.overpaid_minor > 0"><th scope="row" class="!bg-surface text-left !text-sm !normal-case !tracking-normal font-normal text-ink-muted">Overpaid (credit or refund)</th><td class="tbl-num" x-text="$fmt.money(b?.overpaid_minor)"></td></tr>
        </tfoot>
    </table>
    <p class="mt-2 text-xs text-ink-muted" x-show="b?.duration_minutes !== undefined" x-text="`Duration ${b?.duration_human ?? $fmt.minutes(b?.duration_minutes)} · ${b?.currency ?? 'LKR'} · calculated by the server`"></p>
</div>
