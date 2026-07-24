<?php

declare(strict_types=1);

namespace App\Support\I18n;

final class NonOverridableTranslationKeys
{
    /**
     * @var list<string>
     */
    private const array PREFIXES = [
        'auth.',
        'api.errors.',
        'admin.errors.',
        'admin.translation_overrides.',
        'admin.components.confirm_delete.',
        'menu.confirm.',
        'tables.halls.confirm.',
        'tables.tables.confirm.',
    ];

    /**
     * @var list<string>
     */
    private const array EXACT_KEYS = [
        'menu.actions.archive',
        'menu.actions.force_delete',
        'menu.actions.restore',
        'menu.category_archived',
        'menu.flash.category_archived',
        'menu.flash.category_force_deleted',
        'menu.flash.category_restored',
        'menu.flash.item_archived',
        'menu.flash.item_force_deleted',
        'menu.flash.item_restored',
        'menu.restore_parent_category_first',
        'tables.halls.actions.archive',
        'tables.halls.actions.force_delete',
        'tables.halls.actions.restore',
        'tables.halls.flash.archived',
        'tables.halls.flash.force_deleted',
        'tables.halls.flash.restored',
        'tables.restore_hall_first',
        'tables.tables.actions.archive',
        'tables.tables.actions.force_delete',
        'tables.tables.actions.restore',
        'tables.tables.flash.archived',
        'tables.tables.flash.force_deleted',
        'tables.tables.flash.restored',
        'admin.nav.translation_overrides',
    ];

    public function contains(string $key): bool
    {
        if (in_array($key, self::EXACT_KEYS, true)) {
            return true;
        }

        foreach (self::PREFIXES as $prefix) {
            if (str_starts_with($key, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
