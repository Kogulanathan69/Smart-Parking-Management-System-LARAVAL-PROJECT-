import { api } from '../lib/api';
import { TZ } from '../lib/format';

export function registerShell(Alpine) {
    Alpine.data('clock', () => ({
        t: '', d: '',
        init() { this.tick(); setInterval(() => this.tick(), 15000); },
        tick() {
            const now = new Date();
            this.t = new Intl.DateTimeFormat('en-GB', { timeZone: TZ, hour: '2-digit', minute: '2-digit', hour12: false }).format(now);
            this.d = new Intl.DateTimeFormat('en-GB', { timeZone: TZ, weekday: 'short', day: '2-digit', month: 'short' }).format(now);
        },
    }));

    // Local recognition engine availability (via Laravel; the browser never calls the Python service).
    Alpine.data('engineStatus', () => ({
        label: 'ANPR …', cls: 'chip-neutral', title: 'Checking the recognition engine',
        init() { this.check(); setInterval(() => this.check(), 60000); },
        async check() {
            try {
                const r = await api.get('/anpr/health');
                const ok = r.anpr_engine === 'available';
                this.label = ok ? 'ANPR engine online' : 'ANPR engine offline';
                this.cls = ok ? 'chip-success' : 'chip-warning';
                this.title = ok ? 'Plate recognition is available' : 'Plate recognition is unavailable. Use manual identification; staff can still check vehicles in and out.';
            } catch { this.label = 'ANPR status unknown'; this.cls = 'chip-neutral'; }
        },
    }));

    Alpine.data('unreadBadge', () => ({
        count: 0,
        init() { this.load(); setInterval(() => this.load(), 120000); window.addEventListener('pf:notifications', () => this.load()); },
        async load() {
            try { const r = await api.get('/notifications'); this.count = (r.data || []).filter((n) => !n.read_at).length; } catch { /* header badge is best-effort */ }
        },
    }));

    Alpine.data('twoFactor', () => ({
        enabled: false, setup: false, needsPassword: false, busy: false, password: '', qr: '', secret: '', code: '', error: null, codes: [], pending: null,
        init() { this.enabled = !!Alpine.store('app').user?.two_factor_enabled; },
        async guard(action) {
            const s = await api.get('web:/user/confirmed-password-status');
            if (!s.confirmed) { this.needsPassword = true; this.pending = action; return false; }
            return true;
        },
        async confirmPassword() {
            this.busy = true;
            try {
                await api.post('web:/user/confirm-password', { password: this.password });
                this.needsPassword = false; this.password = '';
                const next = this.pending; this.pending = null; if (next) await this[next]();
            } catch (e) { window.ParkFlow.toast(e.errors?.password?.[0] || e.message, 'danger'); } finally { this.busy = false; }
        },
        async run(fn) { this.busy = true; try { await fn(); } catch (e) { if (e.status === 423) { this.needsPassword = true; } else window.ParkFlow.toast(e.message, 'danger'); } finally { this.busy = false; } },
        enable() { return this.run(async () => {
            if (!(await this.guard('enable'))) return;
            await api.post('web:/user/two-factor-authentication', {});
            this.qr = (await api.get('web:/user/two-factor-qr-code')).svg;
            this.secret = (await api.get('web:/user/two-factor-secret-key')).secretKey;
            this.setup = true;
        }); },
        confirmCode() { return this.run(async () => {
            this.error = null;
            try { await api.post('web:/user/confirmed-two-factor-authentication', { code: this.code }); }
            catch (e) { this.error = e.errors?.code?.[0] || e.message; return; }
            this.setup = false; this.enabled = true; Alpine.store('app').user.two_factor_enabled = true;
            window.ParkFlow.toast('Two-step verification is on.');
            await this.showCodes();
        }); },
        showCodes() { return this.run(async () => { if (!(await this.guard('showCodes'))) return; this.codes = await api.get('web:/user/two-factor-recovery-codes'); }); },
        regenerate() { return this.run(async () => { if (!(await this.guard('regenerate'))) return; await api.post('web:/user/two-factor-recovery-codes', {}); this.codes = await api.get('web:/user/two-factor-recovery-codes'); window.ParkFlow.toast('New recovery codes generated. Old codes no longer work.'); }); },
        async disable(ask = false) {
            if (ask && !(await window.ParkFlow.confirm({ title: 'Turn off two-step verification?', body: 'Your account will be protected by your password only.', confirmLabel: 'Turn off', tone: 'danger' }))) return;
            return this.run(async () => {
                if (!(await this.guard('disable'))) return;
                await api.del('web:/user/two-factor-authentication');
                Object.assign(this, { enabled: false, setup: false, codes: [], code: '' });
                Alpine.store('app').user.two_factor_enabled = false;
                if (ask) window.ParkFlow.toast('Two-step verification is off.', 'warning');
            });
        },
    }));
}
