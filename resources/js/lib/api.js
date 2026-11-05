/**
 * Same-origin JSON client for /api/v1 (Fortify session cookie + CSRF).
 * - Sends X-XSRF-TOKEN from Laravel's XSRF-TOKEN cookie on every state-changing request.
 * - Never stores credentials in browser storage; never talks to the local ANPR service directly.
 * - Normalises errors into ApiError { status, code, message, errors, context }.
 */
const BASE = '/api/v1';

function xsrfToken() {
    const m = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
    return m ? decodeURIComponent(m[1]) : null;
}

const FRIENDLY = {
    0: 'Cannot reach the server. Check the network connection and try again.',
    401: 'Your session has ended. Please sign in again.',
    403: 'You do not have permission to do that.',
    404: 'That record could not be found.',
    409: 'This action conflicts with the current state of the record.',
    410: 'This item is no longer available.',
    413: 'The file is too large.',
    419: 'Your session expired. Reload the page and try again.',
    422: 'Please check the highlighted fields.',
    429: 'Too many requests. Please wait a moment and try again.',
    500: 'The server ran into a problem. Please try again.',
    502: 'A dependent service is unavailable. Please try again shortly.',
    503: 'The service is temporarily unavailable.',
};

export class ApiError extends Error {
    constructor(status, body) {
        const msg = (body && typeof body.message === 'string' && body.message && !/^Server Error$/i.test(body.message) && status !== 500)
            ? body.message : (FRIENDLY[status] || `Request failed (${status}).`);
        super(msg);
        this.status = status;
        this.body = body || {};
        this.code = body?.error_code || null;
        this.errors = body?.errors || {};
        this.context = body?.context || null;
    }
    firstError(field) { const e = this.errors[field]; return Array.isArray(e) ? e[0] : e; }
}

function buildUrl(path, query) {
    // "web:/user/password" targets a web (Fortify) route; everything else is relative to /api/v1.
    const target = path.startsWith('web:') ? path.slice(4) : (path.startsWith(BASE) ? path : BASE + path);
    const url = new URL(target, window.location.origin);
    if (query) {
        Object.entries(query).forEach(([k, v]) => {
            if (v === undefined || v === null || v === '') return;
            if (Array.isArray(v)) v.forEach((x) => url.searchParams.append(`${k}[]`, x));
            else url.searchParams.set(k, v);
        });
    }
    return url.toString();
}

let sessionExpiredShown = false;

export async function request(method, path, { json, form, query, timeout = 30000, signal, raw = false } = {}) {
    const headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
    let body;
    if (json !== undefined) { headers['Content-Type'] = 'application/json'; body = JSON.stringify(json); }
    if (form !== undefined) body = form; // FormData: browser sets multipart boundary
    if (method !== 'GET') { const t = xsrfToken(); if (t) headers['X-XSRF-TOKEN'] = t; }

    const ctrl = new AbortController();
    const timer = setTimeout(() => ctrl.abort('timeout'), timeout);
    if (signal) signal.addEventListener('abort', () => ctrl.abort('cancelled'));

    let res;
    try {
        res = await fetch(buildUrl(path, query), { method, headers, body, credentials: 'same-origin', signal: ctrl.signal });
    } catch (e) {
        clearTimeout(timer);
        const err = new ApiError(0, { message: ctrl.signal.reason === 'timeout' ? 'The request timed out. It may still have been processed — check before retrying.' : FRIENDLY[0] });
        err.timedOut = ctrl.signal.reason === 'timeout';
        throw err;
    }
    clearTimeout(timer);

    if (raw) return res;
    let data = null;
    const text = await res.text();
    if (text) { try { data = JSON.parse(text); } catch { data = { message: null }; } }

    if (res.status === 401 && !sessionExpiredShown) {
        sessionExpiredShown = true;
        window.location.assign('/login?expired=1');
    }
    if (!res.ok && res.status !== 202) throw new ApiError(res.status, data);
    return { status: res.status, data: data ?? {} };
}

export const api = {
    get: (p, query, o = {}) => request('GET', p, { ...o, query }).then((r) => r.data),
    post: (p, json, o = {}) => request('POST', p, { ...o, json }),
    put: (p, json, o = {}) => request('PUT', p, { ...o, json }),
    patch: (p, json, o = {}) => request('PATCH', p, { ...o, json }),
    del: (p, o = {}) => request('DELETE', p, o),
    upload: (p, formData, o = {}) => request('POST', p, { ...o, form: formData }),
};

/** RFC 4122 v4 for idempotency keys (crypto-strong). One key per settlement attempt. */
export function uuid() {
    if (crypto.randomUUID) return crypto.randomUUID();
    const b = crypto.getRandomValues(new Uint8Array(16));
    b[6] = (b[6] & 0x0f) | 0x40; b[8] = (b[8] & 0x3f) | 0x80;
    const h = [...b].map((x) => x.toString(16).padStart(2, '0')).join('');
    return `${h.slice(0, 8)}-${h.slice(8, 12)}-${h.slice(12, 16)}-${h.slice(16, 20)}-${h.slice(20)}`;
}

/** Normalise both Laravel paginator shapes ({data,meta} resource and plain {current_page,data}). */
export function pageOf(payload) {
    const meta = payload?.meta || (payload && 'current_page' in payload ? payload : null);
    return {
        rows: Array.isArray(payload?.data) ? payload.data : (Array.isArray(payload) ? payload : []),
        page: meta?.current_page ?? 1,
        lastPage: meta?.last_page ?? 1,
        total: meta?.total ?? (Array.isArray(payload?.data) ? payload.data.length : 0),
        from: meta?.from ?? null,
        to: meta?.to ?? null,
        paginated: !!meta,
    };
}

/** Multipart upload with progress (XHR), same CSRF/session rules and error shape as request(). */
export function uploadWithProgress(path, formData, onProgress = () => {}, { timeout = 60000 } = {}) {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open('POST', BASE + path);
        xhr.timeout = timeout;
        xhr.withCredentials = true;
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        const t = xsrfToken(); if (t) xhr.setRequestHeader('X-XSRF-TOKEN', t);
        xhr.upload.onprogress = (e) => { if (e.lengthComputable) onProgress(Math.round((e.loaded / e.total) * 100)); };
        xhr.onload = () => {
            let data = {}; try { data = xhr.responseText ? JSON.parse(xhr.responseText) : {}; } catch { data = {}; }
            if (xhr.status === 401) window.location.assign('/login?expired=1');
            // 503 from /anpr/recognize still carries the stored event (engine_error) — return it to the caller.
            if (xhr.status >= 200 && xhr.status < 300) resolve({ status: xhr.status, data });
            else reject(Object.assign(new ApiError(xhr.status, data), { data }));
        };
        xhr.onerror = () => reject(new ApiError(0, {}));
        xhr.ontimeout = () => reject(Object.assign(new ApiError(0, { message: 'The upload timed out. Try again with the same photo.' }), { timedOut: true }));
        xhr.send(formData);
    });
}
