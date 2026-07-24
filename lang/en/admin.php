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
        'tables' => 'Halls & Tables',
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
    'translation_overrides' => [
        'errors' => [
            'tenant_context_required' => 'Tenant context is required to manage translation overrides.',
            'invalid_locale' => 'Choose one of the supported interface languages.',
            'translation_key_missing' => 'The translation key must exist in the application language files.',
            'key_not_overridable' => 'This translation key cannot be overridden.',
            'value_too_long' => 'The override text is too long.',
        ],
    ],
    'errors' => [
        'eyebrow' => 'Request status',
        'actions' => [
            'dashboard' => 'Back to dashboard',
        ],
        '403' => [
            'title' => 'Access denied',
            'message' => 'You do not have permission to open this admin page.',
        ],
        '404' => [
            'title' => 'Page not found',
            'message' => 'The requested admin resource does not exist in this workspace.',
        ],
        '500' => [
            'title' => 'Unexpected error',
            'message' => 'The request failed unexpectedly. Try again or contact support with the request id.',
        ],
    ],
];
