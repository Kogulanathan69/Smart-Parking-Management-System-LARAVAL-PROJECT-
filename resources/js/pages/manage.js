import { api } from '../lib/api';
import { toast, confirmAction } from '../lib/ui';
import { rupeesToMinor, minorToRupeesInput } from '../lib/format';

/** UI hints for setting inputs (mirrors SettingController::RULES; the server still validates). */
export const SETTING_META = {
    'pricing.grace_minutes': { type: 'int', unit: 'minutes', label: 'Grace period after package end' },
    'pricing.billing_block_minutes': { type: 'int', unit: 'minutes', label: 'Overtime billing block' },
    'pricing.overtime_starts_from': { type: 'enum', options: ['package_end', 'grace_end'], label: 'Overtime starts from' },
    'pricing.night_end_time': { type: 'time', label: 'Night package ends at' },
    'pricing.early_exit_method': { type: 'enum', options: ['none', 'prorate_daily'], label: 'Early-exit credit method' },
    'pricing.early_exit_minimum_days': { type: 'int', unit: 'days', label: 'Early-exit minimum days' },
    'pricing.tax_basis_points': { type: 'int', unit: 'basis points (100 = 1%)', label: 'Tax rate', warn: 'Tax rules are pending client confirmation.' },
    'refund.default_resolution': { type: 'enum', options: ['credit', 'refund'], label: 'Default overpayment handling' },
    'refund.auto_approve_limit_minor': { type: 'money', label: 'Auto-approve refunds up to' },
    'refund.credit_expiry_days': { type: 'int', unit: 'days', nullable: true, label: 'Customer credit expires after' },
    'safety.inspections_per_day': { type: 'int', label: 'Safety inspections per day' },
    'safety.require_exit_inspection': { type: 'bool', label: 'Require exit inspection for safety parking' },
    'exit.release_slot_on': { type: 'enum', options: ['settlement', 'gate'], label: 'Release slot on', warn: '"gate" requires staff to confirm each departure.' },
    'notifications.notify_admins_on_damage': { type: 'bool', label: 'Notify admins about damage' },
    'attendance.late_after_minutes': { type: 'int', unit: 'minutes', label: 'Shift check-in counts as late after' },
    'reminders.enabled': { type: 'bool', label: 'Expiry reminders' },
    'reminders.before_expiry_minutes': { type: 'intlist', unit: 'minutes', label: 'Remind customers before expiry' },
    'reminders.notify_on_expiry': { type: 'bool', label: 'Notify customers when a package expires' },
    'anpr.min_confidence_percent': { type: 'int', unit: '%', label: 'Minimum OCR confidence', warn: 'Tune only after measuring accuracy on site (evaluate.py).' },
    'anpr.verification_mode': { type: 'enum', options: ['always', 'below_threshold'], label: 'Staff verification', warn: '"always" is the safe default. Change only with client approval based on measured results.' },
    'anpr.duplicate_window_seconds': { type: 'int', unit: 'seconds', label: 'Duplicate reading window' },
    'anpr.recognition_timeout_ms': { type: 'int', unit: 'ms', label: 'Recognition timeout' },
    'anpr.camera_retry_attempts': { type: 'int', label: 'Engine retry attempts' },
    'anpr.snapshot_retention_days': { type: 'int', unit: 'days', label: 'Keep plate images for', warn: 'Retention period is a pending client (privacy) decision.' },
    'anpr.store_full_frame': { type: 'bool', label: 'Store full camera frames (not only the plate crop)', warn: 'Full frames may show people; keep off unless approved.' },
    'anpr.health_check_interval_seconds': { type: 'int', unit: 'seconds', label: 'Camera heartbeat interval' },
    'anpr.camera_offline_after_seconds': { type: 'int', unit: 'seconds', label: 'Mark camera offline after' },
    'anpr.manual_override_roles': { type: 'roles', options: ['staff', 'admin', 'super_admin'], label: 'Roles allowed to correct / override readings' },
};

export function settingsPage() {
    return {
        rows: [], history: [], loading: true, error: null, editing: null, value: null, errors: {}, busy: false, tab: 'all', meta: SETTING_META,
        async init() { await this.load(); },
        async load() {
            this.loading = true;
            try { this.rows = (await api.get('/admin/settings')).data; this.history = (await api.get('/admin/settings/history')).data || []; this.error = null; }
            catch (e) { this.error = e.message; } finally { this.loading = false; }
        },
        get groups() { return [...new Set(this.rows.map((r) => r.key.split('.')[0]))]; },
        visible() { return this.rows.filter((r) => this.tab === 'all' || r.key.startsWith(this.tab + '.')); },
        display(r) {
            const m = SETTING_META[r.key] || {}; const v = r.value;
            if (v === null) return 'Not set';
            if (m.type === 'bool') return v ? 'On' : 'Off';
            if (m.type === 'money') return this.$fmt.money(v);
            if (Array.isArray(v)) return v.map((x) => (m.type === 'roles' ? this.$fmt.roleLabel(x) : x)).join(', ') + (m.unit && m.type !== 'roles' ? ` ${m.unit}` : '');
            if (m.type === 'enum') return this.$fmt.titleCase(v);
            return `${v}${m.unit ? ' ' + m.unit : ''}`;
        },
        edit(r) {
            const m = SETTING_META[r.key] || { type: 'text' };
            this.editing = r; this.errors = {};
            this.value = m.type === 'money' ? minorToRupeesInput(r.value) : m.type === 'intlist' ? (r.value || []).join(', ') : m.type === 'roles' ? [...(r.value || [])] : r.value;
        },
        async save() {
            const m = SETTING_META[this.editing.key] || { type: 'text' };
            let v = this.value;
            if (m.type === 'int') v = v === '' || v === null ? (m.nullable ? null : v) : Number(v);
            if (m.type === 'money') v = rupeesToMinor(v);
            if (m.type === 'intlist') v = String(v).split(',').map((x) => x.trim()).filter(Boolean).map(Number);
            if (m.warn && !(await confirmAction({ title: `Change "${m.label || this.editing.key}"?`, body: `${m.warn}\nThe change is versioned and audited.`, confirmLabel: 'Save change' }))) return;
            this.busy = true; this.errors = {};
            try { await api.put(`/admin/settings/${this.editing.key}`, { value: v }); toast('Setting saved.'); this.editing = null; await this.load(); }
            catch (e) { this.errors = e.errors || {}; toast(e.message, 'danger'); } finally { this.busy = false; }
        },
    };
}

export function systemStatus() {
    return {
        d: null, health: null, error: null,
        async init() {
            try { this.d = (await api.get('/admin/system/status')).data; } catch (e) { this.error = e.message; }
            try { this.health = (await api.get('/reports/anpr/camera-health')).data; } catch { this.health = null; }
        },
    };
}

export function zoneDetail(id) {
    return {
        z: null, slots: [], ref: null, loading: true, error: null, notFound: false, busy: false, edit: false, f: {}, errors: {},
        cap: {}, slot: null, slotForm: { status: 'available', notes: '' }, filter: '',
        async init() { this.ref = await this.$store.app.reference(); await this.reload(); },
        async reload() {
            this.loading = true;
            try {
                const [z, s] = await Promise.all([api.get(`/admin/zones/${id}`), api.get(`/admin/zones/${id}/slots`)]);
                this.z = z.data; this.slots = s.data;
                this.cap = Object.fromEntries((this.z.occupancy || []).map((o) => [o.vehicle_category, o.total]));
                this.error = null;
            } catch (e) { this.error = e.message; this.notFound = e.status === 404; } finally { this.loading = false; }
        },
        catByCode(code) { return this.ref?.vehicle_categories.find((c) => c.code === code); },
        startEdit() { this.f = { name: this.z.name, description: this.z.description || '', is_active: this.z.is_active, vehicle_category_ids: (this.z.vehicle_categories || []).map((c) => this.catByCode(c)?.id).filter(Boolean) }; this.errors = {}; this.edit = true; },
        async save() {
            this.busy = true; this.errors = {};
            try { await api.patch(`/admin/zones/${id}`, this.f); toast('Zone saved.'); this.edit = false; await this.reload(); }
            catch (e) { this.errors = e.errors || {}; toast(e.message, 'danger'); } finally { this.busy = false; }
        },
        async setCapacity(code) {
            const cat = this.catByCode(code); const capacity = parseInt(this.cap[code], 10);
            if (!cat || Number.isNaN(capacity)) return;
            this.busy = true;
            try { await api.put(`/admin/zones/${id}/capacity`, { vehicle_category_id: cat.id, capacity }); toast(`Capacity for ${cat.name} set to ${capacity}.`); await this.reload(); }
            catch (e) { toast(e.message, 'danger'); await this.reload(); } finally { this.busy = false; }
        },
        get shownSlots() { return this.slots.filter((s) => !s.decommissioned && (!this.filter || s.status === this.filter)); },
        openSlot(s) { if (s.status === 'occupied') { toast('An occupied slot changes only through Check-In / Check-Out.', 'info'); return; } this.slot = s; this.slotForm = { status: s.status, notes: s.notes || '' }; },
        async saveSlot() {
            this.busy = true;
            try { await api.patch(`/admin/slots/${this.slot.id}/status`, this.slotForm); toast(`Slot ${this.slot.code} updated.`); this.slot = null; await this.reload(); }
            catch (e) { toast(e.message, 'danger'); } finally { this.busy = false; }
        },
    };
}

export function packageDetail(id) {
    return {
        p: null, rates: [], ref: null, loading: true, error: null, notFound: false, busy: false, edit: false, f: {}, errors: {},
        rate: { vehicle_category_id: '', price: '', overtime: '', cap: '', grace_minutes: 15, effective_from: '' }, rateErrors: {}, rateOpen: false,
        async init() { this.ref = await this.$store.app.reference(); await this.reload(); },
        async reload() {
            this.loading = true;
            try { const [p, r] = await Promise.all([api.get(`/admin/packages/${id}`), api.get(`/admin/packages/${id}/rates`)]); this.p = p.data; this.rates = r.data; this.error = null; }
            catch (e) { this.error = e.message; this.notFound = e.status === 404; } finally { this.loading = false; }
        },
        catName(cid) { return this.ref?.vehicle_categories.find((c) => c.id === cid)?.name || `#${cid}`; },
        startEdit() {
            const zt = (this.ref?.zone_types || []).filter((z) => (this.p.zone_types || []).includes(z.code)).map((z) => z.id);
            this.f = { name: this.p.name, description: this.p.description || '', is_active: this.p.is_active, guarantees_slot: this.p.guarantees_slot, allows_early_exit_credit: this.p.allows_early_exit_credit, zone_type_ids: zt };
            this.errors = {}; this.edit = true;
        },
        async save() {
            this.busy = true; this.errors = {};
            try { await api.patch(`/admin/packages/${id}`, this.f); toast('Package saved.'); this.edit = false; await this.reload(); }
            catch (e) { this.errors = e.errors || {}; toast(e.message, 'danger'); } finally { this.busy = false; }
        },
        openRate(r = null) {
            this.rate = r ? { vehicle_category_id: r.vehicle_category_id, price: minorToRupeesInput(r.price_minor), overtime: minorToRupeesInput(r.overtime_hourly_minor), cap: minorToRupeesInput(r.overtime_daily_cap_minor), grace_minutes: r.grace_minutes, effective_from: '' }
                : { vehicle_category_id: '', price: '', overtime: '', cap: '', grace_minutes: 15, effective_from: '' };
            this.rateErrors = {}; this.rateOpen = true;
        },
        async publishRate() {
            const body = { vehicle_category_id: this.rate.vehicle_category_id, price_minor: rupeesToMinor(this.rate.price), overtime_hourly_minor: this.rate.overtime === '' ? null : rupeesToMinor(this.rate.overtime),
                overtime_daily_cap_minor: this.rate.cap === '' ? null : rupeesToMinor(this.rate.cap), grace_minutes: this.rate.grace_minutes === '' ? null : Number(this.rate.grace_minutes), effective_from: this.rate.effective_from || null };
            if (body.price_minor === null) { this.rateErrors = { price_minor: ['Enter a price like 1500 or 1500.50.'] }; return; }
            if (!(await confirmAction({ title: 'Publish a new rate version?', body: `${this.catName(Number(body.vehicle_category_id))}: ${this.$fmt.money(body.price_minor)}. Vehicles already parked keep the rate from their Check-In.`, confirmLabel: 'Publish rate' }))) return;
            this.busy = true; this.rateErrors = {};
            try { await api.post(`/admin/packages/${id}/rates`, body); toast('New rate published.'); this.rateOpen = false; await this.reload(); }
            catch (e) { this.rateErrors = e.errors || {}; toast(e.message, 'danger'); } finally { this.busy = false; }
        },
    };
}

export function cameraDetail(id) {
    return {
        c: null, health: null, loading: true, error: null, notFound: false, busy: false, edit: false, f: {}, errors: {}, token: null,
        async init() { await this.reload(); },
        async reload() {
            this.loading = true;
            try { const [c, h] = await Promise.all([api.get(`/admin/anpr/cameras/${id}`), api.get(`/admin/anpr/cameras/${id}/health`)]); this.c = c.data; this.health = h.data; this.error = null; }
            catch (e) { this.error = e.message; this.notFound = e.status === 404; } finally { this.loading = false; }
        },
        startEdit() { this.f = { name: this.c.name, direction: this.c.direction, camera_type: this.c.camera_type, gate_code: this.c.gate_code || '', stream_url: '', stream_username: '', stream_password: '' }; this.errors = {}; this.edit = true; },
        async save() {
            const body = { ...this.f }; ['stream_url', 'stream_username', 'stream_password'].forEach((k) => { if (!body[k]) delete body[k]; }); if (!body.gate_code) body.gate_code = null;
            this.busy = true; this.errors = {};
            try { await api.patch(`/admin/anpr/cameras/${id}`, body); toast('Camera saved.'); this.edit = false; await this.reload(); }
            catch (e) { this.errors = e.errors || {}; toast(e.message, 'danger'); } finally { this.busy = false; }
        },
        async toggle() {
            const on = !this.c.is_enabled;
            if (!on && !(await confirmAction({ title: 'Disable this camera?', body: 'Its agent will be refused until it is enabled again. Staff can still upload photos or type plates.', confirmLabel: 'Disable', tone: 'danger' }))) return;
            this.busy = true; try { await api.patch(`/admin/anpr/cameras/${id}`, { is_enabled: on }); toast(on ? 'Camera enabled.' : 'Camera disabled.'); await this.reload(); } catch (e) { toast(e.message, 'danger'); } finally { this.busy = false; }
        },
        async rotate() {
            if (!(await confirmAction({ title: 'Rotate the device token?', body: 'The current token stops working immediately. Update the camera agent configuration with the new token.', confirmLabel: 'Rotate token', tone: 'danger', requireText: this.c.code }))) return;
            this.busy = true; try { const r = await api.post(`/admin/anpr/cameras/${id}/rotate-token`); this.token = r.data.device_token ?? r.data.data?.device_token; await this.reload(); } catch (e) { toast(e.message, 'danger'); } finally { this.busy = false; }
        },
    };
}

export function userDetail(id) {
    return {
        u: null, loading: true, error: null, notFound: false, busy: false, status: { open: false, value: 'active', reason: '' }, role: { open: false, value: '' },
        async init() { await this.reload(); },
        async reload() { this.loading = true; try { this.u = (await api.get(`/admin/users/${id}`)).data; this.error = null; } catch (e) { this.error = e.message; this.notFound = e.status === 404; } finally { this.loading = false; } },
        get isSelf() { return this.u?.id === this.$store.app.user?.id; },
        async saveStatus() {
            this.busy = true;
            try { await api.patch(`/admin/users/${id}/status`, { status: this.status.value, reason: this.status.reason || undefined }); toast('Account status updated.'); this.status.open = false; await this.reload(); }
            catch (e) { toast(e.message, 'danger'); } finally { this.busy = false; }
        },
        async saveRole() {
            if (!(await confirmAction({ title: `Change role to ${this.$fmt.roleLabel(this.role.value)}?`, body: 'Their menus and permissions change on the next page load.', confirmLabel: 'Change role' }))) return;
            this.busy = true;
            try { await api.patch(`/admin/users/${id}/role`, { role: this.role.value }); toast('Role changed.'); this.role.open = false; await this.reload(); }
            catch (e) { toast(e.message, 'danger'); } finally { this.busy = false; }
        },
    };
}

export function pricingCalc() {
    return {
        ref: null, f: { parking_package_id: '', vehicle_category_id: '', entry_at: '', exit_at: '', with_safety: false }, quote: null, errors: {}, busy: false,
        async init() { this.ref = await this.$store.app.reference(); },
        async run() {
            this.busy = true; this.errors = {}; this.quote = null;
            try { this.quote = (await api.post('/admin/pricing/preview', { ...this.f, entry_at: this.f.entry_at.replace('T', ' '), exit_at: this.f.exit_at.replace('T', ' ') })).data.quote; }
            catch (e) { this.errors = e.errors || {}; toast(e.message, 'danger'); } finally { this.busy = false; }
        },
    };
}

export function registerManage(Alpine) {
    Object.entries({ settingsPage, systemStatus, zoneDetail, packageDetail, cameraDetail, userDetail, pricingCalc }).forEach(([k, v]) => Alpine.data(k, v));
}
