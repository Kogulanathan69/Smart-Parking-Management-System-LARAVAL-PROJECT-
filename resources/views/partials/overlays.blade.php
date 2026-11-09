{{-- Toasts (polite live region) --}}
<div class="pointer-events-none fixed inset-x-0 bottom-0 z-[60] flex flex-col items-center gap-2 p-4 sm:items-end" aria-live="polite" aria-atomic="false" x-data>
    <template x-for="t in $store.toasts.items" :key="t.id">
        <div class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-xl border px-4 py-3 text-sm shadow-[var(--shadow-pop)]"
             :class="{ 'bg-success border-success-line text-success-ink': t.tone==='success', 'bg-danger border-danger-line text-danger-ink': t.tone==='danger', 'bg-warning border-warning-line text-warning-ink': t.tone==='warning', 'bg-info border-info-line text-info-ink': t.tone==='info' }"
             :role="t.tone==='danger' ? 'alert' : 'status'">
            <span class="flex-1 font-medium" x-text="t.message"></span>
            <button type="button" class="opacity-70 hover:opacity-100" @click="$store.toasts.dismiss(t.id)" aria-label="Dismiss"><x-icon name="x" class="h-4 w-4"/></button>
        </div>
    </template>
</div>

{{-- Confirmation dialog (focus trapped, Escape cancels) --}}
<div x-data x-show="$store.confirm.open" x-cloak class="fixed inset-0 z-[70] grid place-items-center bg-navy-deep/50 p-4" @keydown.escape.window="$store.confirm.open && $store.confirm.answer(false)">
    <div class="card w-full max-w-md p-5" role="alertdialog" aria-modal="true" aria-labelledby="confirm-title" aria-describedby="confirm-body" x-trap.noscroll="$store.confirm.open">
        <h2 id="confirm-title" class="text-lg font-semibold" x-text="$store.confirm.title"></h2>
        <p id="confirm-body" class="mt-2 whitespace-pre-line text-sm text-ink-muted" x-text="$store.confirm.body"></p>
        <template x-if="$store.confirm.requireText">
            <div class="mt-3"><label class="label" for="confirm-type">Type <span class="font-mono font-bold" x-text="$store.confirm.requireText"></span> to confirm</label>
                <input id="confirm-type" class="input" x-model="$store.confirm.typed" autocomplete="off"></div>
        </template>
        <template x-if="$store.confirm.input">
            <div class="mt-3"><label class="label" for="confirm-input" x-text="$store.confirm.input"></label>
                <input id="confirm-input" class="input" x-model="$store.confirm.value" maxlength="255" autocomplete="off" @keydown.enter.prevent="$store.confirm.answer(true)"></div>
        </template>
        <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <button type="button" class="btn-secondary" @click="$store.confirm.answer(false)">Cancel</button>
            <button type="button" :class="$store.confirm.tone==='danger' ? 'btn-danger' : 'btn-primary'" @click="$store.confirm.answer(true)"
                    :disabled="$store.confirm.blocked" x-text="$store.confirm.confirmLabel"></button>
        </div>
    </div>
</div>
