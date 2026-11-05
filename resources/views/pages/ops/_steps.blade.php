<ol class="mb-5 grid grid-cols-4 gap-2 text-xs sm:text-sm" aria-label="Progress">
    @foreach ($steps as $key => $label)
        <li class="flex items-center gap-2 rounded-lg border px-2 py-2 sm:px-3"
            :class="stepIndex >= {{ $loop->index }} ? 'border-brand-strong bg-brand-faint text-brand-stronger' : 'border-line bg-surface text-ink-muted'"
            :aria-current="step === '{{ $key }}' ? 'step' : null">
            <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full text-xs font-bold" :class="stepIndex > {{ $loop->index }} ? 'bg-brand-strong text-white' : (stepIndex === {{ $loop->index }} ? 'bg-navy text-white' : 'bg-neutral text-ink-muted')">{{ $loop->iteration }}</span>
            <span class="truncate font-semibold">{{ $label }}</span>
        </li>
    @endforeach
</ol>
