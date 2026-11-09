<div class="card">
    <div class="table-wrap"><table class="tbl">
        <thead><tr><th scope="col">Vehicle</th><th scope="col">Reference</th><th scope="col">Status</th><th scope="col">Zone / slot</th><th scope="col">Package</th><th scope="col">Checked in</th><th scope="col">Time</th><th scope="col" class="tbl-num">Charge</th></tr></thead>
        <tbody>
            <x-list-states cols="8" empty="No sessions match these filters."/>
            <template x-for="s in rows" :key="s.uuid"><tr>
                <td>@if (!empty($link))<a :href="`{{ $link }}${s.uuid}`" class="hover:no-underline">@endif<span class="plate text-xs" x-text="s.vehicle?.plate_number || s.vehicle?.identifier"></span>@if (!empty($link))</a>@endif
                    <span class="block text-xs text-ink-muted" x-text="`${$fmt.titleCase(s.vehicle_category)} · ${$fmt.titleCase(s.fuel_type)}`"></span></td>
                <td class="whitespace-nowrap">@if (!empty($link))<a :href="`{{ $link }}${s.uuid}`" x-text="s.reference"></a>@else<span x-text="s.reference"></span>@endif</td>
                <td><x-state kind="session" value="s.status"/></td>
                <td class="whitespace-nowrap" x-text="`${s.zone?.code ?? '—'} / ${s.slot?.code ?? '—'}`"></td>
                <td x-text="s.package?.name"></td>
                <td class="whitespace-nowrap" x-text="$fmt.dateTime(s.entry_at)"></td>
                <td><x-state kind="time" value="s.time?.status"/></td>
                <td class="tbl-num" x-text="s.total_charge_minor != null ? $fmt.money(s.total_charge_minor) : '—'"></td>
            </tr></template>
        </tbody>
    </table></div>
    <x-pager/>
</div>
