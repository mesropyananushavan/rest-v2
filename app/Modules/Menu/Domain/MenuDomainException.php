<?php

declare(strict_types=1);

namespace App\Modules\Menu\Domain;

use RuntimeException;

final class MenuDomainException extends RuntimeException
{
    private function __construct(
        private readonly string $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function branchContextRequired(): self
    {
        return new self('menu.branch_context_required', 'Menu item operations require a resolved branch context.');
    }

    public static function categoryArchived(): self
    {
        return new self('menu.category_archived', 'Menu items cannot be restored while their category is archived.');
    }

    public static function restoreParentCategoryFirst(): self
    {
        return new self('menu.restore_parent_category_first', 'Restore the parent category before restoring this subcategory.');
    }

    public static function invalidCategoryParent(): self
    {
        return new self('menu.invalid_category_parent', 'Menu subcategories must belong to a root category in the current tenant.');
    }

    public static function categoryParentChangeBlocked(): self
    {
        return new self('menu.category_parent_change_blocked', 'Menu categories with subcategories or items cannot be moved.');
    }

    public static function itemCategoryMustBeSubcategory(): self
    {
        return new self('menu.item_category_must_be_subcategory', 'Menu items must belong to a subcategory.');
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }
}
