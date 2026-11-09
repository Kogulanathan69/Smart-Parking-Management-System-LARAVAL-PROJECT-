import { api, uuid, uploadWithProgress } from '../lib/api';
import { toast, confirmAction } from '../lib/ui';
import { rupeesToMinor } from '../lib/format';

/** Generic "load one record" state with loading / error / not-found. */
function record(loader) {
    return {
        loading: true, error: null, notFound: false,
        async reload() {
            this.loading = true; this.error = null;
            try { await loader.call(this); } catch (e) { this.error = e.message; this.notFound = e.status === 404; } finally { this.loading = false; }
        },
    };
}

export function sessionDetail(id) {
    return {
        ...record(async function () {
            const [s, bill, pens, gate] = await Promise.all([
                api.get(`/staff/sessions/${id}`), api.get(`/staff/sessions/${id}/bill`),
                api.get(`/staff/sessions/${id}/penalties`), api.get(`/staff/sessions/${id}/gate`),
            ]);
            this.s = s.data; this.payments = s.payments || []; this.inspections = s.inspections || [];
            this.bill = bill.data; this.penalties = pens.data || []; this.gate = gate.data || [];
        }),
        s: null, bill: null, payments: [], inspections: [], penalties: [], gate: [], types: [], busy: false,
        penalty: { penalty_type_id: '', reason: '' }, prepay: { method: 'cash', amount: '', tendered: '', external_reference: '', key: null },
        open: { penalty: false, prepay: false, inspection: false }, inspection: { type: 'routine', condition: 'good', notes: '', new_damage_found: false }, ticket: null,
        async init() {
            await this.reload();
            try { this.types = (await api.get('/staff/penalty-types')).data; } catch { this.types = []; }
            this.ref = await this.$store.app.reference();
        },
        ref: null,
        get isOpen() { return ['active', 'exit_authorized'].includes(this.s?.status); },
        get pendingPayments() { return this.payments.filter((p) => p.status === 'pending'); },
        async run(fn, ok) { if (this.busy) return; this.busy = true; try { await fn(); if (ok) toast(ok); await this.reload(); } catch (e) { toast(e.message, 'danger'); } finally { this.busy = false; } },
        applyPenalty() { return this.run(async () => { await api.post(`/staff/sessions/${id}/penalties`, this.penalty); this.open.penalty = false; this.penalty = { penalty_type_id: '', reason: '' }; }, 'Penalty applied.'); },
        async waive(p) {
            const reason = await confirmAction({ title: `Waive "${p.type?.name}"?`, body: 'The penalty stays on record as waived.', input: 'Reason (required)', inputRequired: true, confirmLabel: 'Waive penalty', tone: 'danger' });
            if (reason === false) return;
            return this.run(() => api.post(`/admin/penalties/${p.id}/waive`, { reason }), 'Penalty waived.');
        },
        openPrepay() { this.prepay = { method: 'cash', amount: '', tendered: '', external_reference: '', key: uuid() }; this.open.prepay = true; },
        doPrepay() {
            const amount = rupeesToMinor(this.prepay.amount);
            if (!amount) { toast('Enter a valid amount.', 'danger'); return; }
            const body = { idempotency_key: this.prepay.key, payment_method: this.prepay.method, amount_minor: amount };
            if (this.prepay.method === 'cash' && this.prepay.tendered) body.tendered_minor = rupeesToMinor(this.prepay.tendered);
            if (this.prepay.external_reference) body.external_reference = this.prepay.external_reference;
            return this.run(async () => { await api.post(`/staff/sessions/${id}/payments`, body); this.open.prepay = false; }, 'Prepayment recorded.');
        },
        async resolvePayment(p, confirmIt) {
            const v = await confirmAction(confirmIt
                ? { title: 'Confirm this payment was received?', body: 'Check the gateway or bank first. The exit completes if this payment settles it.', input: 'Gateway / bank reference (optional)', confirmLabel: 'Payment received' }
                : { title: 'Mark payment as failed?', input: 'Reason', inputRequired: true, confirmLabel: 'Mark failed', tone: 'danger' });
            if (v === false) return;
            return this.run(() => api.post(`/staff/payments/${p.id}/${confirmIt ? 'confirm' : 'fail'}`, confirmIt ? { external_reference: v || undefined } : { reason: v }), confirmIt ? 'Payment confirmed.' : 'Payment marked failed.');
        },
        recordInspection() { return this.run(async () => { await api.post(`/staff/sessions/${id}/inspections`, this.inspection); this.open.inspection = false; }, 'Inspection recorded.'); },
        async printTicket() {
            try { this.ticket = (await api.get(`/staff/sessions/${id}/ticket`)).ticket; this.$nextTick(() => window.print()); } catch (e) { toast(e.message, 'danger'); }
        },
        async reissue() {
            if (!(await confirmAction({ title: 'Reissue the ticket?', body: 'The old QR code stops working. Use this for a lost ticket (apply the lost-ticket penalty first if your policy requires it).', confirmLabel: 'Reissue' }))) return;
            try { this.ticket = (await api.post(`/staff/sessions/${id}/ticket/reissue`)).data.ticket; toast('New ticket issued. The old QR is revoked.'); this.$nextTick(() => window.print()); } catch (e) { toast(e.message, 'danger'); }
        },
        retryGate() { return this.run(() => api.post(`/staff/sessions/${id}/gate/exit`), 'Gate command sent.'); },
        async confirmLeft() {
            if (!(await confirmAction({ title: 'Confirm the vehicle has left?', body: 'The session completes and the slot is released.', confirmLabel: 'Vehicle has left' }))) return;
            return this.run(() => api.post(`/staff/sessions/${id}/confirm-left`), 'Departure confirmed.');
        },
    };
}

export function anprEventDetail(id) {
    return {
        ...record(async function () { this.e = (await api.get(`/staff/anpr/events/${id}`)).data; }),
        e: null, init() { this.reload(); },
        get flowUrl() { return this.e ? `/ops/check-${this.e.direction === 'entry' ? 'in' : 'out'}?event=${this.e.uuid}` : '#'; },
    };
}

export function inspectionsPage() {
    return {
        due: [], loading: true, error: null, current: null, form: { condition: 'good', notes: '', new_damage_found: false }, file: null, busy: false, progress: 0,
        async init() { await this.load(); },
        async load() { this.loading = true; this.error = null; try { this.due = (await api.get('/staff/inspections/due', { hours: 2 })).data; } catch (e) { this.error = e.message; } finally { this.loading = false; } },
        start(i) { this.current = i; this.form = { condition: 'good', notes: '', new_damage_found: false }; this.file = null; },
        async submit() {
            if (this.busy) return; this.busy = true;
            try {
                const r = await api.post(`/staff/sessions/${this.current.session_uuid}/inspections`, { type: this.current.type, safety_inspection_id: this.current.id, ...this.form });
                if (this.file) {
                    const fd = new FormData(); fd.append('attachable_type', 'inspection'); fd.append('attachable_id', r.data.data.id); fd.append('file', this.file);
                    await uploadWithProgress('/staff/evidence', fd, (p) => { this.progress = p; });
                }
                toast('Inspection recorded.'); this.current = null; await this.load();
            } catch (e) { toast(e.message, 'danger'); } finally { this.busy = false; }
        },
    };
}

export function incidentForm() {
    return {
        data: { type: 'damage', severity: 'medium', description: '', parking_session_uuid: '' }, errors: {}, busy: false, file: null, done: null,
        init() { const s = new URLSearchParams(window.location.search).get('session'); if (s) this.data.parking_session_uuid = s; },
        async submit() {
            if (this.busy) return; this.busy = true; this.errors = {};
            try {
                const body = { ...this.data }; if (!body.parking_session_uuid) delete body.parking_session_uuid;
                const r = await api.post('/staff/incidents', body);
                this.done = r.data.data;
                if (this.file) {
                    const fd = new FormData(); fd.append('attachable_type', 'incident'); fd.append('attachable_id', this.done.id); fd.append('file', this.file);
                    try { await uploadWithProgress('/staff/evidence', fd); } catch (e) { toast(`Incident saved, but the photo failed: ${e.message}`, 'warning'); }
                }
                toast(`Incident ${this.done.reference} reported.`);
            } catch (e) { this.errors = e.errors || {}; toast(e.message, 'danger'); } finally { this.busy = false; }
        },
    };
}

export function myShifts() {
    return {
        rows: [], loading: true, error: null, busy: false,
        async init() { await this.load(); },
        async load() { this.loading = true; try { this.rows = (await api.get('/staff/shifts/mine')).data; this.error = null; } catch (e) { this.error = e.message; } finally { this.loading = false; } },
        async act(a, kind) {
            if (this.busy) return; this.busy = true;
            try { await api.post(`/staff/shifts/${a.id}/check-${kind}`); toast(kind === 'in' ? 'Shift started.' : 'Shift ended.'); await this.load(); }
            catch (e) { toast(e.message, 'danger'); } finally { this.busy = false; }
        },
    };
}
