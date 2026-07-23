<?php

declare(strict_types=1);

return [
    'branch_context_required' => 'Select a branch before changing halls.',
    'halls' => [
        'index' => [
            'title' => 'Halls',
            'eyebrow' => 'Halls & Tables',
            'heading' => 'Halls',
            'subtitle' => 'Manage the operating halls for the selected branch.',
        ],
        'form' => [
            'create_title' => 'Create hall',
            'edit_title' => 'Edit hall',
        ],
        'fields' => [
            'actions' => 'Actions',
            'active' => 'Active',
            'color' => 'Color',
            'name' => 'Name',
            'name_en' => 'Name, English',
            'name_hy' => 'Name, Armenian',
            'name_ru' => 'Name, Russian',
            'sort_order' => 'Sort order',
            'status' => 'Status',
        ],
        'actions' => [
            'archive' => 'Archive',
            'back' => 'Back',
            'cancel' => 'Cancel',
            'create' => 'Create hall',
            'edit' => 'Edit',
            'force_delete' => 'Delete forever',
            'restore' => 'Restore',
            'save' => 'Save',
            'show_inactive' => 'Show inactive',
        ],
        'archive_modes' => [
            'active' => 'Active',
            'archived' => 'Archived',
            'all' => 'All',
        ],
        'status' => [
            'active' => 'Active',
            'archived' => 'Archived',
            'inactive' => 'Inactive',
        ],
        'confirm' => [
            'archive_title' => 'Archive hall?',
            'archive_message' => 'This hides the hall from normal workflows. A superadmin can restore it later.',
            'force_delete_title' => 'Delete hall forever?',
            'force_delete_message' => 'This permanently deletes the archived hall. This action is irreversible.',
        ],
        'empty' => [
            'title' => 'No halls yet.',
            'body' => 'Create the first hall for this branch.',
        ],
        'flash' => [
            'created' => 'Hall created.',
            'updated' => 'Hall updated.',
            'archived' => 'Hall archived.',
            'restored' => 'Hall restored.',
            'force_deleted' => 'Hall permanently deleted.',
        ],
    ],
];
