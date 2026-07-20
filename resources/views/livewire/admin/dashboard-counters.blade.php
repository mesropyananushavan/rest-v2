<div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
    <div>
        <x-card class="h-full">
            <p class="mb-2 text-xs font-bold uppercase tracking-[0.18em] text-smartrest-muted">{{ __('admin.dashboard.metrics.categories.label') }}</p>
            <div class="flex items-end gap-2">
                <span class="text-5xl font-extrabold leading-none text-smartrest-ink md:text-6xl">{{ $categoryCount }}</span>
                <span class="mb-2 text-sm text-smartrest-muted">{{ __('admin.dashboard.metrics.categories.unit') }}</span>
            </div>
            <p class="mt-3 text-sm leading-6 text-smartrest-muted">{{ __('admin.dashboard.metrics.categories.help') }}</p>
        </x-card>
    </div>

    <div>
        <x-card class="h-full">
            <p class="mb-2 text-xs font-bold uppercase tracking-[0.18em] text-smartrest-muted">{{ __('admin.dashboard.metrics.items.label') }}</p>
            <div class="flex items-end gap-2">
                <span class="text-5xl font-extrabold leading-none text-smartrest-ink md:text-6xl">{{ $itemCount }}</span>
                <span class="mb-2 text-sm text-smartrest-muted">{{ __('admin.dashboard.metrics.items.unit') }}</span>
            </div>
            <p class="mt-3 text-sm leading-6 text-smartrest-muted">{{ __('admin.dashboard.metrics.items.help') }}</p>
        </x-card>
    </div>
</div>
