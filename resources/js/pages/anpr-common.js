import { api, uuid, uploadWithProgress } from '../lib/api';
import { toast } from '../lib/ui';

export const OVERRIDE_REASONS = [
    ['camera_offline', 'Camera offline'], ['engine_unavailable', 'Recognition engine unavailable'], ['unreadable', 'Plate unreadable'],
    ['low_confidence', 'Low-confidence reading'], ['damaged_plate', 'Damaged or dirty plate'], ['incorrect_result', 'Reading is wrong'], ['no_plate', 'Vehicle has no plate'],
];
const MAX_BYTES = 4096 * 1024; // parking.anpr.image_max_kilobytes
const TYPES = ['image/jpeg', 'image/png']; // parking.anpr.image_mimes (jpg, jpeg, png)

/**
 * Shared capture + verification steps for Check-In and Check-Out.
 * The browser never decides whether a reading is trustworthy: it shows the server's
 * recognition/verification status and sends the staff member's explicit decision.
 */
export function anprFlow(direction) {
    return {
        direction, step: 'capture', mode: 'upload', busy: false,
        cameras: [], cameraCode: '', readings: [], readingsLoaded: false, _poll: null,
        file: null, preview: null, progress: 0, uploadKey: null, uploadError: null,
        manual: { plate_number: '', reason: 'camera_offline', notes: '' }, manualErrors: {},
        event: null, linkOriginal: null, verify: { action: 'confirm', plate_number: '', reason: '', notes: '' }, verifyErrors: {},
        reasons: OVERRIDE_REASONS,

        async initCapture() {
            try {
                const r = await api.get('/staff/anpr/cameras');
                this.cameras = (r.data || []).filter((c) => c.is_enabled && (c.direction === direction || c.direction === 'dual'));
                if (this.cameras.length === 1) this.cameraCode = this.cameras[0].code;
            } catch { this.cameras = []; }
            const qs = new URLSearchParams(window.location.search);
            if (qs.get('event')) { await this.openEvent(qs.get('event')); }
            this.$watch('mode', (m) => (m === 'camera' ? this.startPolling() : this.stopPolling()));
            this.$watch('step', (s) => (s !== 'capture' ? this.stopPolling() : (this.mode === 'camera' && this.startPolling())));
        },
        startPolling() { this.loadReadings(); this.stopPolling(); this._poll = setInterval(() => this.loadReadings(), 5000); },
        stopPolling() { if (this._poll) clearInterval(this._poll); this._poll = null; },
        async loadReadings() {
            try {
                const r = await api.get('/staff/anpr/events', { direction, unused: 1, per_page: 15 });
                this.readings = (r.data || []).filter((e) => e.source === 'camera' && !['rejected'].includes(e.verification_status));
            } catch { /* keep last list */ } finally { this.readingsLoaded = true; }
        },

        pickFile(ev) {
            this.uploadError = null;
            const f = ev.target.files?.[0];
            if (!f) return;
            if (!TYPES.includes(f.type)) { this.uploadError = 'Choose a JPG or PNG photo.'; ev.target.value = ''; return; }
            if (f.size > MAX_BYTES) { this.uploadError = 'The photo is larger than 4 MB. Use a smaller image.'; ev.target.value = ''; return; }
            if (this.preview) URL.revokeObjectURL(this.preview);
            this.file = f; this.preview = URL.createObjectURL(f);
            this.uploadKey = uuid(); // one key per chosen photo → a retried upload never creates a second event
        },
        async upload() {
            if (!this.file || this.busy) return;
            this.busy = true; this.progress = 0; this.uploadError = null;
            const fd = new FormData();
            fd.append('direction', direction); fd.append('image', this.file); fd.append('idempotency_key', this.uploadKey);
            if (this.cameraCode) fd.append('camera_code', this.cameraCode);
            try {
                const r = await uploadWithProgress('/staff/anpr/recognize', fd, (p) => { this.progress = p; });
                await this.openEvent(r.data.data.uuid);
            } catch (e) {
                if (e.status === 503 && e.data?.data?.uuid) {
                    // Stored as engine_error: offer manual identification linked to this event.
                    this.event = e.data.data; this.step = 'review';
                    toast('The recognition engine is unavailable. Identify the vehicle manually.', 'warning');
                } else { this.uploadError = e.errors?.image?.[0] || e.message; }
            } finally { this.busy = false; }
        },
        async manualIdentify(originalUuid = null) {
            if (this.busy) return;
            this.busy = true; this.manualErrors = {};
            try {
                const payload = { direction, ...this.manual, original_event_uuid: originalUuid || this.linkOriginal || undefined };
                if (payload.reason === 'no_plate') delete payload.plate_number;
                const cam = this.cameras.find((c) => c.code === this.cameraCode); if (cam) payload.anpr_camera_id = cam.id;
                const r = await api.post('/staff/anpr/manual-review', payload);
                toast('Manual identification recorded.');
                await this.openEvent(r.data.data.uuid);
            } catch (e) {
                this.manualErrors = e.errors || {};
                toast(e.status === 403 ? 'Your role is not allowed to make manual identifications. Ask an admin.' : e.message, 'danger');
            } finally { this.busy = false; }
        },
        async openEvent(id) {
            const r = await api.get(`/staff/anpr/events/${id}`);
            this.event = r.data;
            this.verify = { action: this.canConfirm ? 'confirm' : 'correct', plate_number: this.event.verified_plate || this.event.normalized_plate || '', reason: '', notes: '' };
            this.verifyErrors = {};
            if (this.event.direction !== direction) { toast(`This is ${this.event.direction} reading — open it from Check-${this.event.direction === 'entry' ? 'In' : 'Out'}.`, 'danger'); this.step = 'capture'; return; }
            this.step = this.isVerified ? 'details' : 'review';
            if (this.step === 'details') await this.loadPreview();
        },
        get stepIndex() { return { capture: 0, review: 1, details: 2, done: 3 }[this.step] ?? 0; },
        get isVerified() { return ['verified', 'corrected'].includes(this.event?.verification_status); },
        get usable() { return this.event && !this.event.consumed_at && !this.event.duplicate_of && this.event.verification_status !== 'rejected'; },
        get crop() { return (this.event?.images || []).find((i) => i.category === 'plate_crop' && i.url) || (this.event?.images || []).find((i) => i.url) || null; },
        get topCandidate() { return (this.event?.candidates || [])[0] || null; },
        get displayPlate() { return this.event?.verified_plate || this.topCandidate?.display || this.event?.raw_text || this.event?.normalized_plate || ''; },
        get canConfirm() { return !!(this.event?.normalized_plate) && !['unreadable', 'engine_error'].includes(this.event?.recognition_status); },

        async submitVerify() {
            if (this.busy) return;
            const v = this.verify;
            if (v.action !== 'confirm' && !v.reason) { this.verifyErrors = { reason: ['Choose a reason.'] }; return; }
            if (v.action === 'correct' && !v.plate_number.trim()) { this.verifyErrors = { plate_number: ['Type the plate exactly as on the vehicle.'] }; return; }
            this.busy = true; this.verifyErrors = {};
            try {
                const body = { action: v.action, notes: v.notes || undefined };
                if (v.action !== 'confirm') body.reason = v.reason;
                if (v.action === 'correct') body.plate_number = v.plate_number.trim();
                await api.post(`/staff/anpr/events/${this.event.uuid}/verify`, body);
                if (v.action === 'reject') { toast('Reading rejected. Capture again or identify manually.', 'warning'); this.resetFlow(); return; }
                toast(v.action === 'confirm' ? 'Plate confirmed.' : 'Plate corrected.');
                await this.openEvent(this.event.uuid);
            } catch (e) { this.verifyErrors = e.errors || {}; toast(e.message, 'danger'); } finally { this.busy = false; }
        },
        resetFlow() {
            if (this.preview) URL.revokeObjectURL(this.preview);
            Object.assign(this, { step: 'capture', event: null, linkOriginal: null, file: null, preview: null, progress: 0, uploadKey: null, uploadError: null,
                manual: { plate_number: '', reason: 'camera_offline', notes: '' } });
            this.onReset?.();
            const input = this.$root.querySelector('input[type=file]'); if (input) input.value = '';
            window.history.replaceState(null, '', window.location.pathname);
            if (this.mode === 'camera') this.startPolling();
        },
    };
}
