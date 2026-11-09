<div class="card"><div class="table-wrap"><table class="tbl">
    <thead><tr><th>Captured</th><th>Direction</th><th>Plate</th><th>Recognition</th><th class="tbl-num">OCR conf.</th><th>Verification</th><th>Source</th><th>Used</th>@if($canAct)<th></th>@endif</tr></thead>
    <tbody><x-list-states cols="{{ $canAct ? 9 : 8 }}" empty="No readings match."/>
        <template x-for="e in rows" :key="e.uuid"><tr>
            <td class="whitespace-nowrap" x-text="$fmt.dateTime(e.captured_at)"></td>
            <td x-text="e.direction === 'entry' ? 'Check-In' : 'Check-Out'"></td>
            <td><span class="plate text-xs" x-text="e.verified_plate || e.raw_text || '—'"></span><span class="block text-xs text-ink-muted" x-show="e.verified_plate && e.raw_text && e.verified_plate !== e.normalized_plate" x-text="`read as ${e.raw_text}`"></span></td>
            <td><x-state kind="recognition" value="e.recognition_status"/></td>
            <td class="tbl-num" x-text="$fmt.percent(e.confidence_percent)"></td>
            <td><x-state kind="verification" value="e.verification_status"/><span class="block text-xs text-ink-muted" x-show="e.override_reason" x-text="$fmt.titleCase(e.override_reason)"></span></td>
            <td class="whitespace-nowrap" x-text="`${$fmt.titleCase(e.source)}${e.camera ? ' · ' + e.camera.code : ''}`"></td>
            <td x-text="e.consumed_at ? 'Yes' : 'No'"></td>
            @if($canAct)<td class="text-right"><a class="btn-secondary btn-sm" :href="`/ops/anpr/${e.uuid}`">Open</a></td>@endif
        </tr></template>
    </tbody></table></div><x-pager/></div>
