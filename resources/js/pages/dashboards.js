import { api } from '../lib/api';

function refreshing(initial, url) {
    return {
        d: initial, busy: false, updated: new Date().toISOString(),
        start() { this._t = setInterval(() => this.refresh(true), 60000); this.after?.(); },
        async refresh(silent = false) {
            this.busy = true;
            try { this.d = (await api.get(`web:${url}`)).data; this.updated = new Date().toISOString(); this.after?.(); }
            catch (e) { if (!silent) window.ParkFlow.toast(e.message, 'danger'); }
            finally { this.busy = false; }
        },
    };
}

export function registerDashboards(Alpine) {
    Alpine.data('staffDashboard', (initial) => ({
        ...refreshing(initial, '/staff/dashboard'),
        pendingReviews: null, expiring: null,
        get free() { return (this.d.occupancy || []).filter((z) => z.is_active).reduce((a, z) => a + z.available, 0); },
        async after() {
            api.get('/staff/anpr/events', { verification_status: 'pending', unused: 1, per_page: 1 }).then((r) => { this.pendingReviews = r.meta?.total ?? r.data?.length ?? 0; }).catch(() => { this.pendingReviews = '—'; });
            api.get('/staff/sessions/expiring', { within: 60 }).then((r) => { this.expiring = r.data || []; }).catch(() => { this.expiring = []; });
        },
    }));

    Alpine.data('opsDashboard', (initial) => ({
        ...refreshing(initial, window.location.pathname),
        get activeZones() { return (this.d.occupancy || []).filter((z) => z.is_active); },
        get totalSpaces() { return this.activeZones.reduce((sum, z) => sum + Number(z.total || 0), 0); },
        get occupiedSpaces() { return this.activeZones.reduce((sum, z) => sum + Number(z.occupied || 0) + Number(z.reserved || 0), 0); },
        get availableSpaces() { return this.activeZones.reduce((sum, z) => sum + Number(z.available || 0), 0); },
        get occupancyPercent() { return this.totalSpaces ? Math.round(this.occupiedSpaces / this.totalSpaces * 100) : 0; },
        get attentionTotal() { return Number(this.d.open_incidents || 0) + Number(this.d.overdue_inspections || 0) + Number(this.d.pending_refunds || 0); },
    }));

    Alpine.data('anprSnapshot', () => ({
        engine: null, cameras: null, pending: null, configured: null,
        async init() {
            try { const h = await api.get('/reports/anpr/camera-health'); this.engine = h.data.engine; this.cameras = h.data.cameras; this.configured = h.data.configured !== false; } catch { this.engine = { available: false, driver: 'unknown' }; this.cameras = []; this.configured = null; }
            try { const r = await api.get('/staff/anpr/events', { verification_status: 'pending', unused: 1, per_page: 1 }); this.pending = r.meta?.total ?? 0; } catch { this.pending = '—'; }
        },
    }));

    Alpine.data('auditorDashboard', (initial) => ({
        d: initial, overview: null,
        async start() { try { this.overview = (await api.get('/reports/overview')).data; } catch { this.overview = null; } },
    }));

    Alpine.data('customerDashboard', (initial) => ({
        d: initial, active: null,
        async start() {
            try { this.active = (await api.get('/customer/sessions', { status: 'active,exit_authorized' })).data; } catch { this.active = []; }
        },
    }));
}
