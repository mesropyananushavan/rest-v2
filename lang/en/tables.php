<?php

declare(strict_types=1);

return [
    'branch_context_required' => 'Select a branch before changing halls and tables.',
    'restore_hall_first' => 'Restore the hall before restoring this table.',
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
            'tables' => 'Tables',
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
    'tables' => [
        'index' => [
            'title' => 'Tables',
            'eyebrow' => 'Hall tables',
            'heading' => 'Tables in :hall',
            'subtitle' => 'Manage service tables for the selected hall.',
        ],
        'form' => [
            'create_title' => 'Create table',
            'edit_title' => 'Edit table',
        ],
        'fields' => [
            'actions' => 'Actions',
            'active' => 'Active',
            'hdm_department' => 'HDM department',
            'is_delivery' => 'Delivery',
            'name' => 'Name',
            'name_en' => 'Name, English',
            'name_hy' => 'Name, Armenian',
            'name_ru' => 'Name, Russian',
            'shape' => 'Shape',
            'sort_order' => 'Sort order',
            'status' => 'Status',
            'type' => 'Type',
        ],
        'actions' => [
            'archive' => 'Archive',
            'back' => 'Back',
            'back_to_halls' => 'Back to halls',
            'cancel' => 'Cancel',
            'create' => 'Create table',
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
        'confirm' => [
            'archive_title' => 'Archive table?',
            'archive_message' => 'This hides the table from normal workflows. A superadmin can restore it later.',
            'force_delete_title' => 'Delete table forever?',
            'force_delete_message' => 'This permanently deletes the archived table. This action is irreversible.',
        ],
        'empty' => [
            'title' => 'No tables yet.',
            'body' => 'Create the first table for this hall.',
        ],
        'empty_value' => 'Not set',
        'flash' => [
            'created' => 'Table created.',
            'updated' => 'Table updated.',
            'archived' => 'Table archived.',
            'restored' => 'Table restored.',
            'force_deleted' => 'Table permanently deleted.',
        ],
        'shapes' => [
            'circle' => 'Circle',
            'square' => 'Square',
            'rectangle' => 'Rectangle',
        ],
        'status' => [
            'active' => 'Active',
            'archived' => 'Archived',
            'inactive' => 'Inactive',
        ],
        'types' => [
            'standard' => 'Standard',
            'vip' => 'VIP',
        ],
        'values' => [
            'yes' => 'Yes',
            'no' => 'No',
        ],
    ],
];
