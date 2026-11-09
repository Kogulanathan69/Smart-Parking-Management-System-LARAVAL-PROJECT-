/**
 * Display formatting only. Amounts arrive from the server as integer minor units (LKR cents);
 * the browser never calculates prices, totals or balances.
 */
export const TZ = 'Asia/Colombo';
const intf = new Intl.NumberFormat('en-LK', { maximumFractionDigits: 0 });

export function money(minor, { currency = 'LKR', blank = '—' } = {}) {
    if (minor === null || minor === undefined || minor === '') return blank;
    const n = Number(minor);
    if (!Number.isFinite(n)) return blank;
    const sign = n < 0 ? '−' : '';
    // whole rupees and cents are formatted separately from integers: no floating-point rounding.
    const abs = Math.abs(Math.trunc(n));
    return `${sign}${currency} ${intf.format(Math.floor(abs / 100))}.${String(abs % 100).padStart(2, '0')}`;
}

/** "1,500.50" / "1500.5" / "1500" → 150050 (exact, string based). Returns null when invalid. */
export function rupeesToMinor(input) {
    if (input === null || input === undefined) return null;
    const s = String(input).replace(/[,\s]/g, '').replace(/^LKR/i, '');
    if (!/^\d{1,12}(\.\d{0,2})?$/.test(s)) return null;
    const [whole, frac = ''] = s.split('.');
    return parseInt(whole, 10) * 100 + parseInt((frac + '00').slice(0, 2), 10);
}
export function minorToRupeesInput(minor) {
    if (minor === null || minor === undefined) return '';
    const n = Math.trunc(Number(minor));
    return `${Math.floor(n / 100)}.${String(Math.abs(n % 100)).padStart(2, '0')}`;
}

const dtf = new Intl.DateTimeFormat('en-GB', { timeZone: TZ, day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: false });
const df = new Intl.DateTimeFormat('en-GB', { timeZone: TZ, day: '2-digit', month: 'short', year: 'numeric' });
const tf = new Intl.DateTimeFormat('en-GB', { timeZone: TZ, hour: '2-digit', minute: '2-digit', hour12: false });

function asDate(v) { if (!v) return null; const d = v instanceof Date ? v : new Date(String(v).replace(' ', 'T')); return Number.isNaN(d.getTime()) ? null : d; }
export const dateTime = (v, blank = '—') => { const d = asDate(v); return d ? dtf.format(d) : blank; };
export const date = (v, blank = '—') => { const d = asDate(v); return d ? df.format(d) : blank; };
export const time = (v, blank = '—') => { const d = asDate(v); return d ? tf.format(d) : blank; };
/** Today's date in Colombo as Y-m-d (for API date filters). */
export function todayLocal(offsetDays = 0) {
    const d = new Date(Date.now() + offsetDays * 86400000);
    const p = Object.fromEntries(new Intl.DateTimeFormat('en-CA', { timeZone: TZ, year: 'numeric', month: '2-digit', day: '2-digit' }).formatToParts(d).map((x) => [x.type, x.value]));
    return `${p.year}-${p.month}-${p.day}`;
}

export function minutes(m, blank = '—') {
    if (m === null || m === undefined) return blank;
    const n = Math.max(0, Math.round(m));
    const d = Math.floor(n / 1440), h = Math.floor((n % 1440) / 60), mm = n % 60;
    return [d && `${d}d`, (d || h) && `${h}h`, `${mm}m`].filter(Boolean).join(' ');
}
export const percent = (v, blank = '—') => (v === null || v === undefined ? blank : `${Number(v).toFixed(Number(v) % 1 ? 1 : 0)}%`);
export const titleCase = (s) => (s ? String(s).replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase()) : '');

/**
 * One vocabulary for every state shown to users. Keys are the exact backend values.
 * tone → chip colour; the text label is always shown, so colour is never the only signal.
 */
const STATES = {
    session: {
        active: ['Parked', 'brand'], exit_authorized: ['Gate Authorized', 'info'], completed: ['Checked out', 'success'], cancelled: ['Cancelled', 'neutral'],
    },
    slot: { available: ['Available', 'success'], occupied: ['Occupied', 'info'], reserved: ['Reserved', 'warning'], unavailable: ['Out of Service', 'neutral'] },
    recognition: {
        recognized: ['Recognised', 'success'], low_confidence: ['Low confidence', 'warning'], unreadable: ['Unreadable', 'danger'],
        engine_error: ['Engine unavailable', 'danger'], duplicate: ['Duplicate reading', 'neutral'], manual: ['Manual entry', 'info'],
    },
    verification: { pending: ['ANPR Review Required', 'warning'], verified: ['Verified', 'success'], corrected: ['Corrected', 'info'], rejected: ['Rejected', 'danger'] },
    payment: { pending: ['Payment Pending', 'warning'], paid: ['Settled', 'success'], failed: ['Failed', 'danger'], refunded: ['Refunded', 'neutral'], unpaid: ['Unpaid', 'warning'] },
    gate: { success: ['Gate opened', 'success'], failed: ['Gate Failed', 'danger'], not_requested: ['Not requested', 'neutral'], simulated: ['Simulated (no hardware)', 'info'] },
    time: {
        no_fixed_expiry: ['Hourly', 'neutral'], active: ['Within package', 'success'], expiring_soon: ['Expiring soon', 'warning'],
        in_grace: ['In grace period', 'warning'], expired: ['Overstay', 'danger'], closed: ['Closed', 'neutral'],
    },
    camera: { online: ['Online', 'success'], offline: ['Offline', 'danger'], error: ['Error', 'danger'], unknown: ['No heartbeat yet', 'neutral'], disabled: ['Disabled', 'neutral'] },
    user: { active: ['Active', 'success'], inactive: ['Inactive', 'neutral'], suspended: ['Suspended', 'danger'] },
    refund: { pending: ['Pending approval', 'warning'], approved: ['Approved — to pay', 'info'], paid: ['Paid out', 'success'], rejected: ['Rejected', 'neutral'] },
    reservation: { active: ['Active', 'success'], expired: ['Expired', 'neutral'], cancelled: ['Cancelled', 'neutral'] },
    inspection: { scheduled: ['Due', 'warning'], completed: ['Completed', 'success'], missed: ['Missed', 'danger'] },
    incident: { open: ['Open', 'danger'], investigating: ['Investigating', 'warning'], resolved: ['Resolved', 'success'], closed: ['Closed', 'neutral'] },
    complaint: { open: ['Open', 'warning'], in_review: ['In review', 'info'], resolved: ['Resolved', 'success'], closed: ['Closed', 'neutral'] },
    shift: { scheduled: ['Scheduled', 'info'], on_leave: ['On leave', 'neutral'], replaced: ['Replaced', 'neutral'], cancelled: ['Cancelled', 'neutral'], checked_in: ['On shift', 'success'], checked_out: ['Shift ended', 'neutral'] },
    penalty: { applied: ['Applied', 'warning'], waived: ['Waived', 'neutral'] },
    severity: { info: ['Info', 'neutral'], low: ['Low', 'info'], medium: ['Medium', 'warning'], high: ['High', 'danger'] },
    condition: { good: ['Good', 'success'], minor_damage: ['Minor damage', 'warning'], major_damage: ['Major damage', 'danger'] },
};
export function stateLabel(kind, value) { return STATES[kind]?.[value]?.[0] ?? (value ? titleCase(value) : '—'); }
export function stateTone(kind, value) { return STATES[kind]?.[value]?.[1] ?? 'neutral'; }
export function chip(kind, value) { return `chip-${stateTone(kind, value)}`; }

export const ROLE_LABELS = { super_admin: 'Super Admin', admin: 'Admin', staff: 'Staff', auditor: 'Auditor', customer: 'Customer' };
export const roleLabel = (r) => ROLE_LABELS[r] || titleCase(r);

export function escapeCsv(v) { const s = v === null || v === undefined ? '' : String(v); return /[",\n]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s; }
export function downloadCsv(filename, header, rows) {
    const csv = [header.map(escapeCsv).join(','), ...rows.map((r) => r.map(escapeCsv).join(','))].join('\r\n');
    const url = URL.createObjectURL(new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8' }));
    const a = Object.assign(document.createElement('a'), { href: url, download: filename });
    document.body.appendChild(a); a.click(); a.remove(); setTimeout(() => URL.revokeObjectURL(url), 1000);
}
