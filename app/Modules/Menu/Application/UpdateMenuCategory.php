<?php

declare(strict_types=1);

namespace App\Modules\Menu\Application;

use App\Modules\Menu\Domain\MenuDomainException;
use App\Modules\Menu\Infrastructure\Models\MenuCategory;
use App\Support\I18n\LocalizedText;

final class UpdateMenuCategory
{
    use RecordsMenuAction;

    public function __invoke(int $categoryId, LocalizedText $name, int $sortOrder, bool $active, ?int $parentId = null): MenuCategory
    {
        $startedAt = microtime(true);

        $category = MenuCategory::query()->findOrFail($categoryId);
        $parent = $this->findValidParent($parentId, (int) $category->id);

        if ((int) ($category->parent_id ?? 0) !== (int) ($parent?->id ?? 0) && $this->hasChildren($category)) {
            throw MenuDomainException::categoryParentChangeBlocked();
        }

        $category->update([
            'parent_id' => $parent === null ? null : (int) $parent->id,
            'translated_name' => $name->toArray(),
            'sort_order' => $sortOrder,
            'active' => $active,
        ]);

        $this->logSuccess('menu.categories.update', $startedAt, [
            'category_id' => (int) $category->id,
        ]);

        return $category;
    }

    private function findValidParent(?int $parentId, int $categoryId): ?MenuCategory
    {
        if ($parentId === null) {
            return null;
        }

        if ($parentId === $categoryId) {
            throw MenuDomainException::invalidCategoryParent();
        }

        // Keep TenantScoped enabled here: a parent from another tenant resolves to null.
        $parent = MenuCategory::query()
            ->whereKey($parentId)
            ->first();

        if (! $parent instanceof MenuCategory || $parent->parent_id !== null) {
            throw MenuDomainException::invalidCategoryParent();
        }

        return $parent;
    }

    private function hasChildren(MenuCategory $category): bool
    {
        return $category->subcategories()->withTrashed()->exists()
            || $category->items()->withTrashed()->exists();
    }
}
