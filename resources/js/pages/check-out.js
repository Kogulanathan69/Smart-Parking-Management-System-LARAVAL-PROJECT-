import { api, uuid } from '../lib/api';
import { toast, confirmAction } from '../lib/ui';
import { rupeesToMinor } from '../lib/format';
import { anprFlow } from './anpr-common';
import { mixin } from '../lib/mixin';

/**
 * Check-Out. The server matches the session (exact plate only), produces the bill, settles
 * the payment and decides about the gate. The browser only collects the staff member's inputs.
 */
export function checkOut() {
    return mixin(anprFlow('exit'), {
        ref: null, previewData: null, previewError: null, sessionChoice: '', sessionSearch: '', sessionResults: [],
        pay: { method: '', tendered: '', external_reference: '', overpayment_resolution: 'credit' }, payErrors: {},
        attemptKey: null, result: null, inspection: { condition: 'good', notes: '', new_damage_found: false }, ticket: { qr: '' }, ticketQuote: null,

        async init() {
            this.ref = await this.$store.app.reference();
            await this.initCapture();
        },
        onReset() { Object.assign(this, { previewData: null, previewError: null, result: null, sessionChoice: '', sessionResults: [], attemptKey: null, ticketQuote: null, payErrors: {} });
            this.pay = { method: '', tendered: '', external_reference: '', overpayment_resolution: 'credit' }; this.ticket = { qr: '' }; },

        async loadPreview() {
            this.previewError = null; this.previewData = null;
            try {
                const body = { event_uuid: this.event.uuid };
                if (this.sessionChoice) body.parking_session_uuid = this.sessionChoice;
                this.previewData = (await api.post('/staff/anpr/exit/preview', body)).data.data;
                if (this.previewData.session && !this.attemptKey) this.attemptKey = uuid();
                if (this.balance === 0) this.pay.method = '';
                else if (!this.pay.method) this.pay.method = 'cash';
            } catch (e) { this.previewError = e; }
        },
        async findSessions() {
            if (!this.sessionSearch.trim()) return;
            try { this.sessionResults = (await api.get('/staff/sessions', { q: this.sessionSearch.trim(), status: 'active,exit_authorized', per_page: 10 })).data; }
            catch (e) { toast(e.message, 'danger'); }
        },
        async chooseSession(s) { this.sessionChoice = s.uuid; await this.loadPreview(); },

        get bill() { return this.previewData?.bill || null; },
        get balance() { return this.bill ? this.bill.balance_minor : null; },
        get methods() { return (this.ref?.payment_methods || []); },
        get method() { return this.methods.find((m) => m.code === this.pay.method) || null; },
        get tenderedMinor() { return this.pay.tendered === '' ? null : rupeesToMinor(this.pay.tendered); },
        // Display aid only; the server records the actual change on the payment.
        get changeHint() { return this.method?.code === 'cash' && this.tenderedMinor !== null && this.balance !== null ? this.tenderedMinor - this.balance : null; },
        get needsInspection() { return !!this.previewData?.requires_exit_inspection; },
        get canSettle() {
            if (!this.previewData?.session || this.needsInspection || this.busy) return false;
            if (this.balance > 0) {
                if (!this.method) return false;
                if (this.method.code === 'cash' && (this.tenderedMinor === null || this.tenderedMinor < this.balance)) return false;
                if (this.method.requires_reference && !this.pay.external_reference.trim()) return false;
            }
            return true;
        },
        get plateForConfirm() { return this.event?.verified_plate || this.previewData?.plate || ''; },

        async recordInspection() {
            if (this.busy) return;
            this.busy = true;
            try {
                await api.post(`/staff/sessions/${this.previewData.session.uuid}/inspections`, { type: 'exit', ...this.inspection });
                toast(this.inspection.new_damage_found ? 'Exit inspection recorded with new damage. An incident may be needed.' : 'Exit inspection recorded.');
                await this.loadPreview();
            } catch (e) { toast(e.message, 'danger'); } finally { this.busy = false; }
        },

        settlementBody() {
            const b = { idempotency_key: this.attemptKey };
            if (this.balance > 0) {
                b.payment_method = this.pay.method;
                if (this.pay.method === 'cash') b.tendered_minor = this.tenderedMinor;
                if (this.method?.requires_reference || this.pay.external_reference) b.external_reference = this.pay.external_reference.trim() || undefined;
            }
            if (this.bill?.overpaid_minor > 0) b.overpayment_resolution = this.pay.overpayment_resolution;
            return b;
        },
        async settle() {
            if (!this.canSettle) return;
            const ok = await confirmAction({
                title: 'Settle and authorise exit?',
                body: this.balance > 0 ? `Take ${this.$fmt.money(this.balance)} by ${this.method?.name}. The server records the payment and then decides whether the gate opens.` : 'Nothing is due. The server will complete the exit.',
                confirmLabel: 'Settle',
            });
            if (!ok) return;
            this.busy = true; this.payErrors = {};
            try {
                const body = { event_uuid: this.event.uuid, confirm_plate: this.plateForConfirm, ...this.settlementBody() };
                if (this.sessionChoice) body.parking_session_uuid = this.sessionChoice;
                const r = await api.post('/staff/anpr/exit/settle', body);
                this.result = r.data; this.step = 'done';
                toast(r.status === 202 ? 'Payment is pending. The gate stays closed until it is confirmed.' : 'Exit settled.', r.status === 202 ? 'warning' : 'success');
            } catch (e) {
                this.payErrors = e.errors || {};
                if (e.timedOut || e.status === 0) toast('No response from the server. Press Settle again: the same attempt key prevents double charging.', 'warning');
                else {
                    toast(e.message, 'danger');
                    if (['verification_required', 'plate_confirmation_mismatch'].includes(e.code)) await this.openEvent(this.event.uuid);
                    else if (e.code === 'exit_inspection_required') await this.loadPreview();
                    else if (e.status === 422 || e.code === 'idempotency_conflict') { /* inputs need fixing: keep key only if nothing was recorded */ if (e.code === 'idempotency_conflict') this.attemptKey = uuid(); }
                }
            } finally { this.busy = false; }
        },

        // ---- result-stage actions (all server-authorised) ----
        get sessionUuid() { return this.result?.session?.uuid; },
        get gateLabel() {
            const g = this.result?.gate; if (!g) return null;
            if (g.status === 'failed') return ['Gate Failed', 'chip-danger', 'The settlement is saved. Retry the gate or open it by hand and report it.'];
            if (g.driver === 'log' || g.status === 'simulated') return ['Gate Authorized', 'chip-info', 'Simulated — no gate hardware is connected. Let the vehicle out by hand.'];
            return ['Gate opened', 'chip-success', `Driver: ${g.driver}`];
        },
        async retryGate() {
            this.busy = true;
            try { const r = await api.post(`/staff/sessions/${this.sessionUuid}/gate/exit`); this.result.gate = r.data.gate; toast('Gate command sent.'); }
            catch (e) { toast(e.message, 'danger'); } finally { this.busy = false; }
        },
        async confirmLeft() {
            this.busy = true;
            try { const r = await api.post(`/staff/sessions/${this.sessionUuid}/confirm-left`); this.result.session = r.data.data; toast('Vehicle departure confirmed; slot released.'); }
            catch (e) { toast(e.message, 'danger'); } finally { this.busy = false; }
        },
        async resolvePending(confirm) {
            const pid = this.result?.payment?.id; if (!pid) return;
            const ref = await confirmAction(confirm
                ? { title: 'Confirm the online payment was received?', body: 'Only confirm after checking the payment in the gateway or bank statement.', input: 'Gateway / bank reference (optional)', confirmLabel: 'Payment received' }
                : { title: 'Mark the payment as failed?', body: 'The session stays open and another payment method can be used.', input: 'Reason', inputRequired: true, confirmLabel: 'Mark failed', tone: 'danger' });
            if (ref === false) return;
            this.busy = true;
            try {
                const r = await api.post(`/staff/payments/${pid}/${confirm ? 'confirm' : 'fail'}`, confirm ? { external_reference: ref || undefined } : { reason: ref || undefined });
                const d = r.data;
                Object.assign(this.result, { payment: d.payment ?? d.data ?? this.result.payment, status: d.status ?? (confirm ? 'completed' : 'payment_failed'), receipt: d.receipt ?? this.result.receipt, gate: d.gate ?? this.result.gate, session: d.session ?? this.result.session });
                toast(confirm ? 'Payment confirmed.' : 'Payment marked failed. Take another payment method.', confirm ? 'success' : 'warning');
                if (!confirm) { this.step = 'details'; this.attemptKey = uuid(); await this.loadPreview(); }
            } catch (e) { toast(e.message, 'danger'); } finally { this.busy = false; }
        },

        // ---- internal QR ticket fallback (legacy /staff/exits endpoints) ----
        async ticketPreview() {
            this.busy = true; this.ticketQuote = null;
            try { this.ticketQuote = (await api.post('/staff/exits/preview', { qr_payload: this.ticket.qr.trim() })).data.quote; this.attemptKey = uuid(); if (!this.pay.method && this.ticketQuote.balance_minor > 0) this.pay.method = 'cash'; }
            catch (e) { toast(e.message, 'danger'); } finally { this.busy = false; }
        },
        async ticketSettle() {
            if (this.busy) return;
            const bal = this.ticketQuote?.balance_minor ?? 0;
            if (bal > 0 && this.pay.method === 'cash' && (this.tenderedMinor === null || this.tenderedMinor < bal)) { toast('Cash tendered is less than the amount due.', 'danger'); return; }
            if (!(await confirmAction({ title: 'Settle this ticket?', body: `Amount due ${this.$fmt.money(bal)}.`, confirmLabel: 'Settle' }))) return;
            this.busy = true;
            try {
                const b = { qr_payload: this.ticket.qr.trim(), idempotency_key: this.attemptKey };
                if (bal > 0) { b.payment_method = this.pay.method; if (this.pay.method === 'cash') b.tendered_minor = this.tenderedMinor; if (this.pay.external_reference) b.external_reference = this.pay.external_reference.trim(); }
                if (this.ticketQuote?.overpaid_minor > 0) b.overpayment_resolution = this.pay.overpayment_resolution;
                const r = await api.post('/staff/exits/settle', b);
                this.result = r.data; this.step = 'done';
            } catch (e) { toast(e.message, 'danger'); if (e.code === 'idempotency_conflict') this.attemptKey = uuid(); } finally { this.busy = false; }
        },
        printReceipt() { window.print(); },
    });
}
