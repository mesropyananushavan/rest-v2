<?php

declare(strict_types=1);

use App\Modules\Menu\Infrastructure\Models\MenuCategory;

/** @var bool $canManageCategories */
/** @var bool $canViewArchive */
/** @var MenuCategory $category */
/** @var array{context?: array<string, int|string>} $menuContext */

$parent = $category->parent;
$canRestore = $category->parent_id === null || ! ($parent instanceof MenuCategory && $parent->trashed());
?>

@if ($canManageCategories)
    <div class="mt-3 flex flex-wrap items-center gap-2">
        @if (! $category->trashed())
            <x-button :href="route('admin.menu.categories.edit', array_merge(['category' => (int) $category->id], $menuContext))" variant="outline-secondary" size="sm">
                {{ __('menu.actions.edit') }}
            </x-button>
            <x-row-overflow id="category_overflow_{{ (int) $category->id }}" :label="__('menu.actions.more')">
                <x-confirm-modal
                    id="archive_category_{{ (int) $category->id }}"
                    :action="route('admin.menu.categories.destroy', array_merge(['category' => (int) $category->id], $menuContext))"
                    :title="__('menu.confirm.archive_category_title')"
                    :message="__('menu.confirm.archive_category_message')"
                    :trigger-label="__('menu.actions.archive')"
                    :confirm-label="__('menu.actions.archive')"
                    :trigger-class="'flex w-full items-center px-3 py-2 text-left text-sm font-semibold text-red-700 transition hover:bg-red-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-smartrest-danger'"
                    role="menuitem"
                />
            </x-row-overflow>
        @elseif ($canViewArchive)
            <x-row-overflow id="category_overflow_{{ (int) $category->id }}" :label="__('menu.actions.more')">
                @if ($canRestore)
                    <form method="post" action="{{ route('admin.menu.categories.restore', array_merge(['category' => (int) $category->id], $menuContext)) }}">
                        @csrf
                        <button type="submit" role="menuitem" class="flex w-full items-center px-3 py-2 text-left text-sm font-semibold text-green-800 transition hover:bg-smartrest-success/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-smartrest-success">
                            {{ __('menu.actions.restore') }}
                        </button>
                    </form>
                @endif
                <x-confirm-modal
                    id="force_delete_category_{{ (int) $category->id }}"
                    :action="route('admin.menu.categories.force-delete', array_merge(['category' => (int) $category->id], $menuContext))"
                    :title="__('menu.confirm.force_delete_category_title')"
                    :message="__('menu.confirm.force_delete_category_message')"
                    :trigger-label="__('menu.actions.force_delete')"
                    :confirm-label="__('menu.actions.force_delete')"
                    :trigger-class="'flex w-full items-center px-3 py-2 text-left text-sm font-semibold text-red-700 transition hover:bg-red-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-smartrest-danger'"
                    role="menuitem"
                />
            </x-row-overflow>
        @endif
    </div>
@endif
