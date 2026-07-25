<?php

declare(strict_types=1);

return [
    'tenant_context_required' => 'Select a tenant before changing orders.',
    'branch_context_required' => 'Select a branch before changing orders.',
    'table_not_found' => 'The selected table is not available in this branch.',
    'table_already_open' => 'This table already has an open order.',
    'order_not_open' => 'Only open orders can be changed.',
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
    'flash' => [
        'opened' => 'Order opened.',
        'waiter_assigned' => 'Waiter assigned.',
        'subtable_added' => 'Subtable added.',
        'cancelled' => 'Order cancelled.',
    ],
];
