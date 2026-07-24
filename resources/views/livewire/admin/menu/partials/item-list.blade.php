<?php

declare(strict_types=1);

use App\Modules\Menu\Domain\MenuItemImageSlot;
use App\Modules\Menu\Infrastructure\Models\MenuItem;
use App\Modules\Menu\Infrastructure\Storage\MenuItemImageUrlResolver;
use App\Support\Money\MoneyFormatter;
use Illuminate\Pagination\LengthAwarePaginator;

/** @var bool $canManageItems */
/** @var bool $canViewArchive */
/** @var MenuItemImageUrlResolver $imageUrls */
/** @var LengthAwarePaginator<int, MenuItem> $items */
/** @var string $locale */
/** @var array{context?: array<string, int|string>} $menuContext */
/** @var bool $showCategory */
?>

<div class="divide-y divide-slate-100">
    @foreach ($items as $item)
        <article class="grid gap-3 p-4 transition hover:bg-slate-50 lg:grid-cols-[4rem_minmax(0,1fr)_9rem_8rem_auto] lg:items-center">
            <img
                src="{{ $imageUrls->thumbnailUrl($item, MenuItemImageSlot::Internal) }}"
                alt="{{ __('menu.images.list_thumbnail_alt') }}"
                class="h-16 w-16 rounded-2xl border border-slate-200 bg-slate-50 object-cover shadow-sm"
            >

            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    @if (! $item->trashed())
                        <a href="{{ route('admin.menu.items.edit', array_merge(['item' => (int) $item->id], $menuContext)) }}" class="font-black text-smartrest-ink no-underline hover:text-smartrest-success">
                            {{ $item->translatedName()->forLocale($locale) }}
                        </a>
                    @else
                        <span class="font-black text-smartrest-ink">{{ $item->translatedName()->forLocale($locale) }}</span>
                    @endif
                    @if ($item->trashed() && $canViewArchive)
                        <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800 ring-1 ring-amber-200">{{ __('menu.status.archived') }}</span>
                    @endif
                </div>
                @if ($item->translatedDescription() !== null)
                    <p class="mt-1 line-clamp-2 text-sm text-smartrest-muted">{{ $item->translatedDescription()?->forLocale($locale) }}</p>
                @endif
                @if ($showCategory)
                    <div class="mt-2 text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">
                        {{ __('menu.fields.category') }}: {{ $item->category?->translatedName()->forLocale($locale) }}
                    </div>
                @endif
            </div>

            <div class="text-sm font-bold text-smartrest-ink">{{ MoneyFormatter::format($item->price(), $locale) }}</div>

            <div class="flex flex-col items-start gap-2">
                <x-badge-status
                    :active="$item->active"
                    :active-label="__('menu.status.active')"
                    :inactive-label="__('menu.status.inactive')"
                />
                @if (! $item->trashed() && $canManageItems)
                    <button
                        type="button"
                        wire:click="toggleItemActivity({{ (int) $item->id }})"
                        class="text-xs font-bold text-smartrest-success underline-offset-4 transition hover:text-green-800 hover:underline"
                    >
                        {{ $item->active ? __('menu.actions.deactivate') : __('menu.actions.activate') }}
                    </button>
                @endif
            </div>

            <div class="flex flex-wrap justify-start gap-2 lg:justify-end">
                @if (! $item->trashed())
                    <x-button :href="route('admin.menu.items.edit', array_merge(['item' => (int) $item->id], $menuContext))" variant="outline-secondary" size="sm">
                        {{ __('menu.actions.edit') }}
                    </x-button>
                @endif
                @if (! $item->trashed() && $canManageItems)
                    <x-confirm-modal
                        id="archive_item_{{ (int) $item->id }}"
                        :action="route('admin.menu.items.destroy', array_merge(['item' => (int) $item->id], $menuContext))"
                        :title="__('menu.confirm.archive_item_title')"
                        :message="__('menu.confirm.archive_item_message')"
                        :trigger-label="__('menu.actions.archive')"
                        :confirm-label="__('menu.actions.archive')"
                    />
                @endif
                @if ($item->trashed() && $canManageItems && $canViewArchive && ! $item->category?->trashed())
                    <form method="post" action="{{ route('admin.menu.items.restore', array_merge(['item' => (int) $item->id], $menuContext)) }}">
                        @csrf
                        <x-button type="submit" variant="outline-primary" size="sm">
                            {{ __('menu.actions.restore') }}
                        </x-button>
                    </form>
                @endif
                @if ($item->trashed() && $canManageItems && $canViewArchive)
                    <x-confirm-modal
                        id="force_delete_item_{{ (int) $item->id }}"
                        :action="route('admin.menu.items.force-delete', array_merge(['item' => (int) $item->id], $menuContext))"
                        :title="__('menu.confirm.force_delete_item_title')"
                        :message="__('menu.confirm.force_delete_item_message')"
                        :trigger-label="__('menu.actions.force_delete')"
                        :confirm-label="__('menu.actions.force_delete')"
                    />
                @endif
            </div>
        </article>
    @endforeach
</div>
