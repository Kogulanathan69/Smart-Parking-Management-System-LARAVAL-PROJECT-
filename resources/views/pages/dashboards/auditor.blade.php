<x-layouts.app title="Auditor dashboard" wide>
<div x-data="auditorDashboard(@js($data))" x-init="start()">
    <x-page-header title="Audit overview" subtitle="Read-only. Nothing on these pages can change parking, payment or gate records."/>
    <div class="alert-info mb-4" role="note"><strong>Read-only access.</strong> You can view, filter, print and download what you see. Audit findings you record are kept as your own notes and do not change any operational record.</div>
    <div class="grid gap-4 xl:grid-cols-3">
        <div class="xl:col-span-2" x-data="{ get fin() { return d.financial_7_days }, get maxDay() { return Math.max(0, ...d.financial_7_days.by_day.map(x => x.revenue_minor ?? x.amount_minor ?? 0)) } }">@include('pages.dashboards._financial')</div>
        <section class="card card-pad"><h2 class="card-title">Where to look</h2>
            <ul class="mt-3 space-y-2 text-sm">
                <li><a href="{{ route('reports.payments') }}">Payments ledger</a> — every payment, method and reference</li>
                <li><a href="{{ route('reports.sessions') }}">Session ledger</a> — Check-In / Check-Out with charges</li>
                <li><a href="{{ route('audit.corrections') }}">ANPR corrections</a> — staff corrections, rejections, manual entries</li>
                <li><a href="{{ route('audit.logs') }}">Audit trail</a> — who did what, and when</li>
                <li><a href="{{ route('reports.anpr') }}">Camera & gate health</a></li>
            </ul></section>
    </div>
    <div class="mt-4" x-show="overview" x-data="{ get occ() { return overview?.occupancy || [] }, pct(z) { return z.total ? Math.round((z.occupied + z.reserved) / z.total * 100) : 0 } }">@include('pages.dashboards._occupancy')</div>
</div>
</x-layouts.app>
