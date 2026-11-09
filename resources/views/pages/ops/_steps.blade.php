<ol class="mb-6 grid grid-cols-2 gap-2 text-xs sm:grid-cols-4 sm:text-sm" aria-label="Progress">
    @foreach ($steps as $key => $label)
        <li class="relative flex items-center gap-2 rounded-xl border px-3 py-3 shadow-sm"
            :class="stepIndex >= {{ $loop->index }} ? 'border-brand-soft bg-brand-faint text-brand-stronger' : 'border-line bg-surface text-ink-muted'"
            :aria-current="step === '{{ $key }}' ? 'step' : null">
            <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full text-xs font-bold" :class="stepIndex >= {{ $loop->index }} ? 'bg-brand-strong text-white' : 'bg-neutral text-ink-muted'">{{ $loop->iteration }}</span>
            <span class="truncate font-semibold">{{ $label }}</span>
        </li>
    @endforeach
</ol>
