<?php

declare(strict_types=1);

use App\Modules\Menu\Infrastructure\Models\MenuCategory;

/** @var bool $canManageCategories */
/** @var bool $canViewArchive */
/** @var MenuCategory $category */

$parent = $category->parent;
$canRestore = $category->parent_id === null || ! ($parent instanceof MenuCategory && $parent->trashed());
?>

@if ($canManageCategories)
    <div class="mt-3 flex flex-wrap gap-2">
        @if (! $category->trashed())
            <x-button :href="route('admin.menu.categories.edit', ['category' => (int) $category->id])" variant="outline-secondary" size="sm">
                {{ __('menu.actions.edit') }}
            </x-button>
            <x-confirm-modal
                id="archive_category_{{ (int) $category->id }}"
                :action="route('admin.menu.categories.destroy', ['category' => (int) $category->id])"
                :title="__('menu.confirm.archive_category_title')"
                :message="__('menu.confirm.archive_category_message')"
                :trigger-label="__('menu.actions.archive')"
                :confirm-label="__('menu.actions.archive')"
            />
        @elseif ($canViewArchive)
            @if ($canRestore)
                <form method="post" action="{{ route('admin.menu.categories.restore', ['category' => (int) $category->id]) }}">
                    @csrf
                    <x-button type="submit" variant="outline-primary" size="sm">
                        {{ __('menu.actions.restore') }}
                    </x-button>
                </form>
            @endif
            <x-confirm-modal
                id="force_delete_category_{{ (int) $category->id }}"
                :action="route('admin.menu.categories.force-delete', ['category' => (int) $category->id])"
                :title="__('menu.confirm.force_delete_category_title')"
                :message="__('menu.confirm.force_delete_category_message')"
                :trigger-label="__('menu.actions.force_delete')"
                :confirm-label="__('menu.actions.force_delete')"
            />
        @endif
    </div>
@endif
