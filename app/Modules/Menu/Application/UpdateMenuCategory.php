<?php

declare(strict_types=1);

namespace App\Modules\Menu\Application;

use App\Modules\Menu\Domain\MenuDomainException;
use App\Modules\Menu\Infrastructure\Models\MenuCategory;
use App\Support\I18n\LocalizedText;
use Illuminate\Support\Facades\DB;

final class UpdateMenuCategory
{
    use RecordsMenuAction;

    public function __invoke(int $categoryId, LocalizedText $name, int $sortOrder, bool $active, ?int $parentId = null): MenuCategory
    {
        $startedAt = microtime(true);

        $category = MenuCategory::query()->findOrFail($categoryId);
        $parent = $this->findValidParent($parentId, (int) $category->id);
        $currentParentId = $category->parent_id === null ? 0 : (int) $category->parent_id;
        $newParentId = $parent instanceof MenuCategory ? (int) $parent->id : 0;

        if ($currentParentId !== $newParentId && $this->hasChildren($category)) {
            throw MenuDomainException::categoryParentChangeBlocked();
        }

        $before = $this->menuCategoryAuditPayload($category);

        DB::transaction(function () use ($active, $category, $name, $parent, $sortOrder, $before): void {
            $category->update([
                'parent_id' => $parent === null ? null : (int) $parent->id,
                'translated_name' => $name->toArray(),
                'sort_order' => $sortOrder,
                'active' => $active,
            ]);

            $this->auditMenuMutation(
                'menu.category.updated',
                'menu_category',
                (int) $category->id,
                $before,
                $this->menuCategoryAuditPayload($category->refresh()),
            );
        });

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
