<?php

declare(strict_types=1);

namespace App\Modules\Menu\Application;

use App\Modules\Menu\Domain\MenuDomainException;
use App\Modules\Menu\Infrastructure\Models\MenuCategory;
use App\Support\I18n\LocalizedText;

final class CreateMenuCategory
{
    use RecordsMenuAction;

    public function __invoke(LocalizedText $name, int $sortOrder = 0, bool $active = true, ?int $parentId = null): MenuCategory
    {
        $startedAt = microtime(true);
        $parent = $this->findValidParent($parentId);

        $category = MenuCategory::query()->create([
            'parent_id' => $parent === null ? null : (int) $parent->id,
            'translated_name' => $name->toArray(),
            'sort_order' => $sortOrder,
            'active' => $active,
        ]);

        $this->logSuccess('menu.categories.create', $startedAt, [
            'category_id' => (int) $category->id,
        ]);

        return $category;
    }

    private function findValidParent(?int $parentId): ?MenuCategory
    {
        if ($parentId === null) {
            return null;
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
}
