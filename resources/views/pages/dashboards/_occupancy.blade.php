{{-- Occupancy by zone from the server's own counts. Expects `occ` (array) in scope. --}}
<section class="card" aria-labelledby="occ-h">
    <div class="flex items-center justify-between gap-2 border-b border-line px-4 py-3">
        <h2 id="occ-h" class="card-title">Occupancy by zone</h2>
        <span class="text-xs text-ink-muted" x-text="typeof updated !== 'undefined' && updated ? `Updated ${$fmt.time(updated)}` : ''"></span>
    </div>
    <div class="table-wrap">
        <table class="tbl">
            <thead><tr><th scope="col">Zone</th><th scope="col" class="w-1/3">Use</th><th scope="col" class="tbl-num">Available</th><th scope="col" class="tbl-num">Occupied</th><th scope="col" class="tbl-num">Reserved</th><th scope="col" class="tbl-num">Out of service</th><th scope="col" class="tbl-num">Total</th></tr></thead>
            <tbody>
            <template x-for="z in occ" :key="z.zone_id">
                <tr>
                    <td class="min-w-36"><span class="block font-semibold" x-text="`Zone ${z.code}`"></span><span class="block text-xs text-ink-muted" x-text="z.name.replace(/^Zone \w+ [–-] /, '')"></span>
                        <span class="chip-neutral mt-1" x-show="!z.is_active">Inactive</span></td>
                    <td><div class="h-2.5 w-full overflow-hidden rounded-full bg-neutral" role="img" :aria-label="`${pct(z)}% in use`">
                        <div class="h-full rounded-full" :class="pct(z) >= 90 ? 'bg-danger-ink' : (pct(z) >= 70 ? 'bg-warning-ink' : 'bg-brand-strong')" :style="`width:${pct(z)}%`"></div></div>
                        <span class="text-xs text-ink-muted" x-text="`${pct(z)}% in use`"></span></td>
                    <td class="tbl-num font-semibold text-success-ink" x-text="z.available"></td>
                    <td class="tbl-num" x-text="z.occupied"></td>
                    <td class="tbl-num" x-text="z.reserved"></td>
                    <td class="tbl-num" x-text="z.unavailable"></td>
                    <td class="tbl-num" x-text="z.total"></td>
                </tr>
            </template>
            <tr x-show="!occ.length"><td colspan="7" class="py-8 text-center text-ink-muted">No zones have been set up yet.</td></tr>
            </tbody>
        </table>
    </div>
</section>
