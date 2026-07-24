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
        'translation_overrides' => 'Translations',
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
        'title' => 'Translations',
        'eyebrow' => 'Tenant wording',
        'heading' => 'Translation overrides',
        'subtitle' => 'Find the exact text an operator sees, review its key and locale values, then override or reset it for this tenant.',
        'search' => [
            'label' => 'Search visible text',
            'placeholder' => 'Type words shown in the UI or a key fragment',
            'help' => 'Matches the effective value for the selected locale. Existing overrides are searched before language-file defaults.',
        ],
        'locale' => [
            'label' => 'Editing language',
            'help' => 'Edits affect one locale at a time.',
        ],
        'results' => [
            'heading' => 'Matching strings',
        ],
        'table' => [
            'effective_value' => 'Effective value',
            'key' => 'Key',
            'status' => 'Status',
            'locale_values' => 'Locale values',
            'actions' => 'Actions',
        ],
        'status' => [
            'default' => 'Default',
            'overridden' => 'Overridden',
        ],
        'actions' => [
            'clear_search' => 'Clear search',
            'edit' => 'Edit',
            'save' => 'Save override',
            'reset' => 'Reset',
        ],
        'edit' => [
            'value_label' => 'Override text',
            'default_value' => 'Language-file default: :value',
        ],
        'flash' => [
            'saved' => 'Translation override saved.',
            'reset' => 'Translation override reset to the language-file default.',
        ],
        'pagination' => [
            'page_of' => 'Page :page of :pages',
            'previous' => 'Previous',
            'next' => 'Next',
        ],
        'empty' => [
            'title' => 'No matching strings',
            'body' => 'Try a different visible word or translation key fragment.',
        ],
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
