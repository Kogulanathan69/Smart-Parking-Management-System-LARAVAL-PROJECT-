<div class="flex flex-wrap items-center justify-between gap-2 border-t border-line px-4 py-3 text-sm" x-show="loaded && rows.length">
    <span class="text-ink-muted" x-text="summary" aria-live="polite"></span>
    <div class="flex items-center gap-2" x-show="paginated && lastPage > 1">
        <button type="button" class="btn-secondary btn-sm" @click="prev()" :disabled="page <= 1 || loading">Previous</button>
        <span class="tabular-nums text-ink-muted" x-text="`Page ${page} of ${lastPage}`"></span>
        <button type="button" class="btn-secondary btn-sm" @click="next()" :disabled="page >= lastPage || loading">Next</button>
    </div>
</div>
