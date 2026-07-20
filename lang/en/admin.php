<?php

declare(strict_types=1);

return [
    'brand' => [
        'name' => 'SmartRest',
        'tagline' => 'Restaurant operations',
    ],
    'nav' => [
        'dashboard' => 'Dashboard',
        'menu' => 'Menu',
    ],
    'actions' => [
        'cancel' => 'Cancel',
        'delete' => 'Delete',
    ],
    'shell' => [
        'toggle_navigation' => 'Toggle navigation',
        'tenant' => 'Tenant',
        'branch' => 'Branch',
        'locale' => 'Locale',
        'no_tenant' => 'No tenant',
        'no_branch' => 'No branch',
        'signed_in_as' => 'Signed in as',
        'switch_branch' => 'Switch branch',
        'switch_locale' => 'Switch locale',
    ],
    'locales' => [
        'hy' => 'Հայ',
        'ru' => 'Рус',
        'en' => 'Eng',
    ],
    'flash' => [
        'branch_updated' => 'Branch changed.',
        'locale_updated' => 'Locale changed.',
    ],
    'components' => [
        'confirm_delete' => [
            'title' => 'Confirm deletion',
            'message' => 'This action cannot be undone.',
        ],
    ],
    'dashboard' => [
        'title' => 'Dashboard',
        'eyebrow' => 'Admin workspace',
        'heading' => 'Welcome, :name',
        'subtitle' => 'A clean operational foundation for the current restaurant workspace.',
        'metrics' => [
            'categories' => [
                'label' => 'Menu categories',
                'unit' => 'total',
                'help' => 'Categories visible in the current tenant.',
            ],
            'items' => [
                'label' => 'Menu items',
                'unit' => 'total',
                'help' => 'Items visible in the current tenant.',
            ],
        ],
    ],
];
