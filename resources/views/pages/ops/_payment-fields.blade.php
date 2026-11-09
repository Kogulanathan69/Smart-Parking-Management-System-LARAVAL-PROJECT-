{{-- Payment inputs; only methods the backend lists. Expects pay, methods, method, payErrors, changeHint in scope. --}}
<template x-if="{{ $balanceExpr }} > 0">
    <div class="space-y-3">
        <fieldset><legend class="label">Payment method</legend>
            <div class="grid grid-cols-3 gap-2">
                <template x-for="m in methods" :key="m.code">
                    <label class="flex cursor-pointer flex-col items-center rounded-lg border p-2 text-center text-sm" :class="pay.method === m.code ? 'border-brand-strong bg-brand-faint font-semibold' : 'border-line'">
                        <input type="radio" class="sr-only" :value="m.code" x-model="pay.method"><span x-text="m.name"></span>
                        <span class="text-[11px] text-ink-muted" x-show="m.confirmation === 'gateway'">confirmed later</span></label>
                </template>
            </div></fieldset>
        <div x-show="pay.method === 'cash'"><label class="label" for="pay-t">Cash received (LKR)</label>
            <input id="pay-t" class="input text-lg tabular-nums" inputmode="decimal" x-model="pay.tendered" placeholder="0.00" autocomplete="off" :aria-invalid="payErrors.tendered_minor ? 'true' : 'false'">
            <p class="field-error" x-show="payErrors.tendered_minor" x-text="payErrors.tendered_minor?.[0]"></p>
            <p class="hint" x-show="pay.tendered !== '' && tenderedMinor === null">Enter an amount like 1500 or 1500.50.</p>
            <p class="mt-1 text-sm" x-show="changeHint !== null && changeHint >= 0">Change to give: <strong x-text="$fmt.money(changeHint)"></strong></p>
            <p class="field-error" x-show="changeHint !== null && changeHint < 0">Cash received is less than the amount due.</p></div>
        <div x-show="method?.requires_reference || pay.method === 'online'"><label class="label" for="pay-r">Reference <span x-show="method?.requires_reference" class="text-danger-ink">*</span></label>
            <input id="pay-r" class="input font-mono" x-model="pay.external_reference" maxlength="100" placeholder="Terminal slip / transaction number">
            <p class="field-error" x-show="payErrors.external_reference" x-text="payErrors.external_reference?.[0]"></p></div>
        <div class="alert-info text-xs" x-show="pay.method === 'online'">Online payments are recorded as <strong>pending</strong>. No payment gateway is connected: confirm the payment only after checking it was received.</div>
    </div>
</template>
<template x-if="!({{ $balanceExpr }} > 0)"><p class="alert-success text-sm">Nothing to pay — the session is fully prepaid.</p></template>
<template x-if="{{ $overpaidExpr }} > 0">
    <div><label class="label" for="pay-o">Overpayment</label><select id="pay-o" class="input" x-model="pay.overpayment_resolution"><option value="credit">Keep as customer credit</option><option value="refund">Refund (needs admin approval)</option></select></div>
</template>
