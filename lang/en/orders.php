<?php

declare(strict_types=1);

return [
    'tenant_context_required' => 'Select a tenant before changing orders.',
    'branch_context_required' => 'Select a branch before changing orders.',
    'table_not_found' => 'The selected table is not available in this branch.',
    'table_already_open' => 'This table already has an open order.',
    'order_not_open' => 'Only open orders can be changed.',
    'menu_item_not_found' => 'The selected menu item is not available for sale in this branch.',
    'currency_mismatch' => 'The menu item currency does not match the order currency.',
    'item_not_in_order' => 'The selected order item is not available in this order.',
    'invalid_quantity' => 'Order item quantity must be at least one.',
    'subtable_not_in_order' => 'The selected subtable does not belong to this order.',
    'invalid_order_type' => 'Unsupported order type.',
    'item_move_noop' => 'The order item is already in the requested location.',
    'order_branch_mismatch' => 'Order items cannot be moved across branches.',
    'types' => [
        'dine_in' => 'Dine in',
        'fast_food' => 'Fast food',
        'takeaway' => 'Takeaway',
        'delivery' => 'Delivery',
    ],
    'status' => [
        'open' => 'Open',
        'closed' => 'Closed',
        'cancelled' => 'Cancelled',
    ],
    'subtables' => [
        'status' => [
            'open' => 'Open',
            'closed' => 'Closed',
        ],
    ],
    'items' => [
        'preparation_status' => [
            'pending' => 'Pending',
        ],
    ],
    'flash' => [
        'opened' => 'Order opened.',
        'waiter_assigned' => 'Waiter assigned.',
        'subtable_added' => 'Subtable added.',
        'cancelled' => 'Order cancelled.',
        'item_added' => 'Item added.',
        'item_qty_changed' => 'Item quantity changed.',
        'item_removed' => 'Item removed.',
        'item_moved' => 'Item moved.',
    ],
];
