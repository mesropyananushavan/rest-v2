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
    'actor_context_required' => 'Payment capture requires an authenticated actor.',
    'capture_amount_must_be_positive' => 'Payment capture amount must be positive.',
    'capture_currency_invalid' => 'Payment capture currency must be a three-letter uppercase ISO code.',
    'idempotency_key_required' => 'Payment capture idempotency key is required.',
    'idempotency_key_too_long' => 'Payment capture idempotency key is too long.',
    'idempotency_key_whitespace' => 'Payment capture idempotency key cannot start or end with whitespace.',
    'idempotency_key_control_characters' => 'Payment capture idempotency key cannot contain control characters.',
    'idempotency_conflict' => 'This idempotency key was already used with different payment capture input.',
    'cashbox_unavailable' => 'The selected cashbox is not available for payment capture.',
    'expected_amount_mismatch' => 'The expected payment amount no longer matches the remaining order balance.',
    'expected_currency_mismatch' => 'The expected payment currency no longer matches the order currency.',
    'order_already_fully_paid' => 'This order is already fully paid.',
    'order_over_allocated' => 'Captured payment allocations exceed the order total.',
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
    'workspace' => [
        'title' => 'Payment',
        'cash_only_note' => 'Cash only. The server recalculates the outstanding amount before capture.',
        'cashbox_placeholder' => 'Select cashbox',
        'default_cashbox' => 'Default',
        'fields' => [
            'outstanding' => 'Outstanding',
            'cashbox' => 'Cashbox',
        ],
        'actions' => [
            'capture_full_cash' => 'Capture full cash payment',
            'capturing' => 'Capturing...',
        ],
        'validation' => [
            'cashbox_required' => 'Select an active cashbox before capturing payment.',
            'cashbox_invalid' => 'Select a valid cashbox before capturing payment.',
        ],
        'unavailable' => [
            'no_cashboxes' => 'No active cashbox is available for this branch.',
        ],
        'errors' => [
            'cashbox_unavailable' => 'The selected cashbox is not available for payment capture.',
            'generic' => 'Payment could not be captured. Refresh the workspace and try again.',
            'stale_amount' => 'The payable amount changed before capture. Review the updated total and try again.',
        ],
        'flash' => [
            'captured' => 'Cash payment captured for :amount.',
        ],
    ],
];
