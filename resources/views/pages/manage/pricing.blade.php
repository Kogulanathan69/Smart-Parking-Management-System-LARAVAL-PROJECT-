<x-layouts.app title="Price calculator">
<div x-data="pricingCalc()">
    <x-page-header title="Price calculator" subtitle="Ask the server what a stay would cost under the current published rates. Nothing is saved."/>
    <div class="alert-info mb-4"><strong>Preview only.</strong> The result uses current server pricing and does not create or modify a parking session.</div>
    <div class="grid gap-4 lg:grid-cols-2">
        <form class="card card-pad grid gap-3 sm:grid-cols-2" @submit.prevent="run()">
            <div><label class="label" for="pc-p">Package</label><select id="pc-p" class="input" x-model="f.parking_package_id" required><option value="">Choose…</option><template x-for="p in ref?.packages || []" :key="p.id"><option :value="p.id" x-text="p.name"></option></template></select></div>
            <div><label class="label" for="pc-c">Vehicle</label><select id="pc-c" class="input" x-model="f.vehicle_category_id" required><option value="">Choose…</option><template x-for="c in ref?.vehicle_categories || []" :key="c.id"><option :value="c.id" x-text="c.name"></option></template></select></div>
            <div><label class="label" for="pc-in">Check-In (Sri Lanka time)</label><input id="pc-in" type="datetime-local" class="input" x-model="f.entry_at" required></div>
            <div><label class="label" for="pc-out">Check-Out</label><input id="pc-out" type="datetime-local" class="input" x-model="f.exit_at" required><p class="field-error" x-show="errors.exit_at" x-text="errors.exit_at?.[0]"></p></div>
            <label class="flex items-center gap-2 text-sm sm:col-span-2"><input type="checkbox" class="check" x-model="f.with_safety"> With safety add-on</label>
            <div class="sm:col-span-2"><button class="btn-primary" :disabled="busy">Calculate</button></div>
        </form>
        <section class="card card-pad" aria-live="polite"><h2 class="card-title">Server quote</h2>
            <p x-show="!quote" class="mt-2 text-sm text-ink-muted">Fill in the form to see the breakdown.</p>
            <div x-show="quote" class="mt-3" x-data="{ get b() { return quote } }">@include('pages.partials._bill')</div></section>
    </div>
</div>
</x-layouts.app>
