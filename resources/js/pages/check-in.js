import { api } from '../lib/api';
import { toast } from '../lib/ui';
import { anprFlow } from './anpr-common';
import { mixin } from '../lib/mixin';

export function checkIn() {
    return mixin(anprFlow('entry'), {
        ref: null, previewData: null, previewError: null, availability: null,
        entry: { vehicle_category_id: '', fuel_type: '', parking_zone_id: '', parking_package_id: '', customer_name: '', customer_phone: '', notes: '' },
        entryErrors: {}, confirmed: false, result: null,

        async init() {
            this.ref = await this.$store.app.reference();
            await this.initCapture();
            this.$watch('entry.vehicle_category_id', () => this.onCategory());
            this.$watch('entry.parking_zone_id', () => { if (!this.packagesForZone.some((p) => String(p.id) === String(this.entry.parking_package_id))) this.entry.parking_package_id = this.packagesForZone.length === 1 ? this.packagesForZone[0].id : ''; });
        },
        onReset() { this.previewData = null; this.previewError = null; this.result = null; this.confirmed = false; this.entryErrors = {};
            this.entry = { vehicle_category_id: '', fuel_type: '', parking_zone_id: '', parking_package_id: '', customer_name: '', customer_phone: '', notes: '' }; },

        async loadPreview() {
            this.previewError = null; this.previewData = null;
            try {
                const r = await api.post('/staff/anpr/entry/preview', { event_uuid: this.event.uuid });
                this.previewData = r.data.data;
                const v = this.previewData.vehicle;
                const cat = v ? this.ref.vehicle_categories.find((c) => c.code === v.vehicle_category) : null;
                this.entry.vehicle_category_id = cat ? cat.id : '';
                this.entry.fuel_type = v?.fuel_type || '';
                const res = this.previewData.active_reservation;
                if (res?.is_valid) {
                    this.entry.parking_zone_id = res.zone_id ?? '';
                    this.entry.parking_package_id = res.package_id ?? '';
                }
                await this.onCategory();
            } catch (e) { this.previewError = e; }
        },
        async onCategory() {
            const cat = this.category;
            if (cat && !(cat.allowed_fuel_types || []).includes(this.entry.fuel_type)) {
                this.entry.fuel_type = cat.allowed_fuel_types?.length === 1 ? cat.allowed_fuel_types[0] : '';
            }
            if (!this.entry.vehicle_category_id) { this.availability = null; return; }
            try { this.availability = (await api.get('/staff/availability', { vehicle_category_id: this.entry.vehicle_category_id })).data; } catch { this.availability = null; }
        },
        get category() { return this.ref?.vehicle_categories.find((c) => String(c.id) === String(this.entry.vehicle_category_id)) || null; },
        get fuelOptions() { const allowed = this.category?.allowed_fuel_types || []; return (this.ref?.fuel_types || []).filter((f) => allowed.includes(f.value)); },
        get zones() {
            return (this.previewData?.zones || []).map((z) => {
                const live = (this.availability || []).find((a) => a.zone_id === z.zone_id);
                const row = live?.by_vehicle_category?.find((b) => b.vehicle_category === this.category?.code);
                const allowed = !!row; // the zone only lists categories it accepts
                return { ...z, allowed, available: row ? row.available : (z.available_for_vehicle ?? null) };
            });
        },
        get selectedZone() { return this.zones.find((z) => String(z.zone_id) === String(this.entry.parking_zone_id)) || null; },
        get packagesForZone() { return this.selectedZone?.packages || []; },
        get selectedPackage() { return this.packagesForZone.find((p) => String(p.id) === String(this.entry.parking_package_id)) || null; },
        get plateForConfirm() { return this.event?.verified_plate || this.previewData?.plate || ''; },
        get readyToConfirm() {
            return this.previewData && !this.previewData.blocked && this.entry.vehicle_category_id && this.entry.parking_zone_id && this.entry.parking_package_id
                && (this.fuelOptions.length === 0 || this.entry.fuel_type) && this.confirmed && this.selectedZone?.available !== 0;
        },

        async confirmEntry() {
            if (this.busy || !this.readyToConfirm) return;
            this.busy = true; this.entryErrors = {};
            try {
                const payload = { event_uuid: this.event.uuid, confirm_plate: this.plateForConfirm, ...this.entry };
                Object.keys(payload).forEach((k) => { if (payload[k] === '') delete payload[k]; });
                const r = await api.post('/staff/anpr/entry/confirm', payload);
                this.result = r.data; this.step = 'done';
                toast(`Checked in ${r.data.data.vehicle?.plate_number || r.data.data.vehicle?.identifier}.`);
            } catch (e) {
                this.entryErrors = e.errors || {};
                if (e.code === 'anpr_event_already_used') toast('This reading was already used for a Check-In. Open the parked vehicles list to find it.', 'danger');
                else if (e.code === 'duplicate_active_entry') toast('This vehicle is already parked. Check it out first.', 'danger');
                else if (e.code === 'no_slot_available') toast('No free slot for this vehicle type in the chosen zone. Pick another zone.', 'danger');
                else toast(e.message, 'danger');
                if (['verification_required', 'plate_confirmation_mismatch'].includes(e.code)) await this.openEvent(this.event.uuid);
            } finally { this.busy = false; }
        },
        printTicket() {
            const w = window.open('', '_blank', 'width=420,height=640');
            if (!w) { toast('Allow pop-ups to print the ticket.', 'warning'); return; }
            const t = this.result.legacy_ticket;
            const esc = (s) => String(s ?? '').replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
            w.document.write(`<!doctype html><title>Ticket ${esc(t.session_reference)}</title><body style="font-family:Segoe UI,Arial;text-align:center;padding:16px">
                <h2 style="margin:0">ParkFlow</h2><p style="margin:4px 0 12px">Parking ticket</p>${t.qr_svg}
                <p><b>${esc(t.session_reference)}</b><br>${esc(t.vehicle)} · Zone ${esc(t.zone)} · ${esc(t.slot)}<br>${esc(t.package)}<br>In: ${esc(t.entry_at_local)}${t.package_ends_at_local ? `<br>Ends: ${esc(t.package_ends_at_local)}` : ''}</p>
                <p style="font-size:11px">Keep this ticket. Times are Sri Lanka time.</p><script>window.onload=()=>{window.print()}<\/script></body>`);
            w.document.close();
        },
    });
}
