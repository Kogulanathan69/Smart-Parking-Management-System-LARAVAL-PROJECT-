import { api, pageOf } from '../lib/api';

/**
 * Server-driven list: backend does search / filter / pagination. Usage:
 *   x-data="dataTable({ url: '/staff/sessions', filters: { plate: '', status: '' } })"
 * Filters are mirrored to the URL query string so lists are linkable and survive reloads.
 */
export function dataTable({ url, filters = {}, perPage = 25, transform = null, auto = true, syncUrl = true, param = {} } = {}) {
    return {
        url, rows: [], loading: false, error: null, loaded: false,
        page: 1, lastPage: 1, total: 0, from: null, to: null, paginated: false,
        filters: { ...filters }, defaults: { ...filters }, perPage, raw: null, _seq: 0,
        init() {
            if (syncUrl) {
                const qs = new URLSearchParams(window.location.search);
                Object.keys(this.filters).forEach((k) => { if (qs.has(k)) this.filters[k] = qs.get(k); });
                if (qs.has('page')) this.page = parseInt(qs.get('page'), 10) || 1;
            }
            if (auto) this.load();
        },
        query() {
            const q = { ...param, page: this.page, per_page: this.perPage };
            Object.entries(this.filters).forEach(([k, v]) => { if (v !== '' && v !== null && v !== undefined && v !== false) q[k] = v; });
            return q;
        },
        async load(page = null) {
            if (page) this.page = page;
            const seq = ++this._seq;
            this.loading = true; this.error = null;
            try {
                const payload = await api.get(typeof this.url === 'function' ? this.url() : this.url, this.query());
                if (seq !== this._seq) return; // a newer request superseded this one
                this.raw = payload;
                const p = pageOf(payload);
                this.rows = transform ? transform(p.rows, payload) : p.rows;
                Object.assign(this, { page: p.page, lastPage: p.lastPage, total: p.total, from: p.from, to: p.to, paginated: p.paginated });
                this.loaded = true;
                if (syncUrl) this.syncQuery();
            } catch (e) {
                if (seq !== this._seq) return;
                this.error = e.message; this.rows = [];
            } finally { if (seq === this._seq) this.loading = false; }
        },
        search() { this.load(1); },
        reset() { this.filters = { ...this.defaults }; this.load(1); },
        next() { if (this.page < this.lastPage) this.load(this.page + 1); },
        prev() { if (this.page > 1) this.load(this.page - 1); },
        syncQuery() {
            const qs = new URLSearchParams();
            Object.entries(this.filters).forEach(([k, v]) => { if (v !== '' && v !== null && v !== undefined && v !== false && v !== this.defaults[k]) qs.set(k, v); });
            if (this.page > 1) qs.set('page', this.page);
            const s = qs.toString();
            window.history.replaceState(null, '', window.location.pathname + (s ? `?${s}` : ''));
        },
        get empty() { return this.loaded && !this.loading && !this.error && this.rows.length === 0; },
        get summary() { return this.paginated && this.total ? `${this.from}–${this.to} of ${this.total}` : `${this.rows.length} shown`; },
    };
}

/**
 * Form helper with server validation errors and double-submit protection.
 *   x-data="form({ name: '' }, async (data) => api.post('/x', data))"
 */
export function form(initial = {}, submitter = null, { reset = false, onSuccess = null } = {}) {
    return {
        data: JSON.parse(JSON.stringify(initial)), initial: JSON.parse(JSON.stringify(initial)),
        errors: {}, busy: false, message: null,
        err(field) { const e = this.errors[field]; return Array.isArray(e) ? e[0] : e || null; },
        async submit(fn = null) {
            if (this.busy) return null; // double-submit guard
            this.busy = true; this.errors = {}; this.message = null;
            try {
                const res = await (fn || submitter)(this.data, this);
                if (reset) this.data = JSON.parse(JSON.stringify(this.initial));
                onSuccess?.(res, this);
                return res;
            } catch (e) {
                this.errors = e.errors || {};
                this.message = e.message;
                window.ParkFlow.toast(e.message, 'danger');
                return null;
            } finally { this.busy = false; }
        },
    };
}

/**
 * List + create/edit modal on top of dataTable(). The server validates and authorises every save;
 * field errors are shown inline from the 422 response.
 */
export function crudPage({ blank = {}, toForm = (r) => ({ ...r }), create = null, update = null, after = null, ...listOpts } = {}) {
    const list = dataTable(listOpts);
    const ui = {
        modal: false, mode: 'create', current: null, f: {}, errors: {}, busy: false,
        openCreate(extra = {}) { this.mode = 'create'; this.current = null; this.f = JSON.parse(JSON.stringify({ ...blank, ...extra })); this.errors = {}; this.modal = true; },
        openEdit(row) { this.mode = 'edit'; this.current = row; this.f = JSON.parse(JSON.stringify(toForm(row))); this.errors = {}; this.modal = true; },
        err(k) { const e = this.errors[k]; return Array.isArray(e) ? e[0] : e || null; },
        async save() {
            if (this.busy) return;
            this.busy = true; this.errors = {};
            try {
                const res = this.mode === 'create' ? await create.call(this, this.f) : await update.call(this, this.current, this.f);
                this.modal = false;
                window.ParkFlow.toast(this.mode === 'create' ? 'Created.' : 'Saved.');
                await after?.call(this, res);
                await this.load();
            } catch (e) { this.errors = e.errors || {}; window.ParkFlow.toast(e.message, 'danger'); } finally { this.busy = false; }
        },
    };
    const target = {};
    for (const o of [list, ui]) Object.defineProperties(target, Object.getOwnPropertyDescriptors(o));
    return target;
}
