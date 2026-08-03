<?php

declare(strict_types=1);

return [
    'tenant_context_required' => 'Tenant context is required to manage cashboxes.',
    'branch_context_required' => 'Select a branch before managing cashboxes.',
    'cashbox_name_required' => 'Cashbox name is required.',
    'cashbox_name_too_long' => 'Cashbox name is too long.',
    'cashbox_name_duplicate' => 'An active cashbox with this name already exists in this branch.',
    'cashbox_default_replacement_required' => 'Choose another active default cashbox before deactivating this one.',
    'cashbox_replacement_invalid' => 'The replacement default cashbox must be active in this branch.',
    'cashbox_default_must_be_active' => 'Only an active cashbox can be selected as default.',
    'cashboxes' => [
        'index' => [
            'title' => 'Cashboxes',
            'eyebrow' => 'Payments',
            'heading' => 'Cashboxes',
            'subtitle' => 'Manage active and inactive cashboxes for the selected branch.',
            'lifecycle_note' => 'Cashboxes are deactivated instead of deleted so future payment history can stay stable.',
        ],
        'form' => [
            'create_title' => 'Create cashbox',
            'edit_title' => 'Edit cashbox',
            'lifecycle_help' => 'New cashboxes are active. The first active cashbox in a branch becomes default automatically.',
        ],
        'fields' => [
            'actions' => 'Actions',
            'active' => 'Active',
            'default' => 'Default',
            'name' => 'Name',
        ],
        'actions' => [
            'activate' => 'Activate',
            'active_only' => 'Show active only',
            'back' => 'Back',
            'cancel' => 'Cancel',
            'create' => 'Create cashbox',
            'deactivate' => 'Deactivate',
            'edit' => 'Edit',
            'make_default' => 'Make default',
            'save' => 'Save',
            'select_replacement_first' => 'Make another cashbox default first',
        ],
        'confirm' => [
            'deactivate_title' => 'Deactivate cashbox?',
            'deactivate_message' => 'This cashbox will stop being available for new payments. It is not deleted.',
        ],
        'empty' => [
            'title' => 'No cashboxes yet.',
            'body' => 'Create the first cashbox for this branch.',
        ],
        'flash' => [
            'created' => 'Cashbox created.',
            'updated' => 'Cashbox updated.',
            'activated' => 'Cashbox activated.',
            'deactivated' => 'Cashbox deactivated.',
            'default_selected' => 'Default cashbox updated.',
        ],
        'status' => [
            'active' => 'Active',
            'inactive' => 'Inactive',
            'default' => 'Default',
            'not_default' => 'Not default',
        ],
    ],
];
