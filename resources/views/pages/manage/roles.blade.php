<x-layouts.app title="Roles & permissions" wide>
<x-page-header title="Roles & permissions" subtitle="Generated from the server's route rules. Permissions are fixed in code and cannot be edited here."/>
<div class="alert-info mb-4" role="note">✔ = the role may call the endpoint. Write actions are marked. Pages follow the same rules, and each record is also checked server-side (customers only ever see their own data). Device endpoints used by camera agents (separate device tokens) are not listed.</div>
<div class="card" x-data="{ q: '', writesOnly: false }">
    <div class="flex flex-wrap items-end gap-3 border-b border-line p-4">
        <div class="w-full sm:w-72"><label class="label" for="rm-q">Filter</label><input id="rm-q" class="input" x-model="q" placeholder="e.g. anpr, refunds, sessions"></div>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="check" x-model="writesOnly"> Write actions only</label>
    </div>
    <div class="table-wrap"><table class="tbl">
        <thead><tr><th scope="col">Area</th><th scope="col">Endpoint</th>@foreach ($roles as $r)<th scope="col" class="text-center">{{ \Illuminate\Support\Str::headline($r) }}</th>@endforeach</tr></thead>
        <tbody>
        @foreach ($rows as $row)
            <tr x-show="(!q || @js(strtolower($row['area'].' '.$row['endpoint'])).includes(q.toLowerCase())) && (!writesOnly || {{ $row['writes'] ? 'true' : 'false' }})">
                <td class="whitespace-nowrap font-medium">{{ $row['area'] }}</td>
                <td class="font-mono text-xs">{{ $row['endpoint'] }} @if($row['writes'])<span class="chip-warning ml-1">write</span>@endif @if($row['any'])<span class="chip-neutral ml-1">any signed-in</span>@endif</td>
                @foreach ($roles as $r)
                    <td class="text-center">@if (in_array($r, $row['roles'], true))<span class="font-bold text-success-ink" aria-label="allowed">✔</span>@else<span class="text-ink-faint" aria-label="not allowed">—</span>@endif</td>
                @endforeach
            </tr>
        @endforeach
        </tbody>
    </table></div>
</div>
</x-layouts.app>
