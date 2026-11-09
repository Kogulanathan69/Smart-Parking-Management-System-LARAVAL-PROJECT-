/** Global UI stores: toasts (aria-live), confirmation dialog, current user/reference data. */
export function registerStores(Alpine) {
    Alpine.store('toasts', {
        items: [],
        push(message, tone = 'success', ms = 5000) {
            const id = Math.random().toString(36).slice(2);
            this.items.push({ id, message, tone });
            if (ms) setTimeout(() => this.dismiss(id), ms);
        },
        dismiss(id) { this.items = this.items.filter((t) => t.id !== id); },
    });

    Alpine.store('confirm', {
        open: false, title: '', body: '', confirmLabel: 'Confirm', tone: 'primary', requireText: null, typed: '', resolve: null,
        input: null, inputRequired: false, value: '',
        /** Resolves true/false; with `input` (a label) resolves the typed string, or false when cancelled. */
        ask({ title, body = '', confirmLabel = 'Confirm', tone = 'primary', requireText = null, input = null, inputRequired = false }) {
            Object.assign(this, { open: true, title, body, confirmLabel, tone, requireText, typed: '', input, inputRequired, value: '' });
            return new Promise((r) => { this.resolve = r; });
        },
        get blocked() { return (this.requireText && this.typed.trim() !== this.requireText) || (this.input && this.inputRequired && !this.value.trim()); },
        answer(ok) {
            if (ok && this.blocked) return;
            this.open = false;
            this.resolve?.(ok ? (this.input ? this.value.trim() : true) : false);
            this.resolve = null;
        },
    });

    const boot = window.__PARKFLOW__ || {};
    Alpine.store('app', {
        user: boot.user || null,
        ref: null,
        refPromise: null,
        can(...roles) { return !!this.user && roles.includes(this.user.role); },
        async reference() {
            if (this.ref) return this.ref;
            if (!this.refPromise) this.refPromise = window.ParkFlow.api.get('/reference-data').then((r) => (this.ref = r.data));
            return this.refPromise;
        },
    });
}

export function toast(message, tone = 'success') { window.Alpine.store('toasts').push(message, tone); }
export function confirmAction(opts) { return window.Alpine.store('confirm').ask(opts); }

/** Show an ApiError: toast + return field errors for inline display. */
export function reportError(err, fallback = 'Something went wrong.') {
    const message = err?.message || fallback;
    toast(message, err?.status === 202 ? 'warning' : 'danger');
    return err?.errors || {};
}
