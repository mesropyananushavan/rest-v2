<?php

declare(strict_types=1);

return [
    'tenant_context_required' => 'Для действий с заказами нужен выбранный ресторанный tenant.',
    'branch_context_required' => 'Выберите филиал для действий с заказами.',
    'table_not_found' => 'Выбранный стол недоступен в этом филиале.',
    'table_already_open' => 'Для этого стола уже есть открытый заказ.',
    'order_not_open' => 'Изменять можно только открытый заказ.',
    'types' => [
        'dine_in' => 'В зале',
        'fast_food' => 'Фастфуд',
        'takeaway' => 'С собой',
        'delivery' => 'Доставка',
    ],
    'status' => [
        'open' => 'Открыт',
        'closed' => 'Закрыт',
        'cancelled' => 'Отменен',
    ],
    'subtables' => [
        'status' => [
            'open' => 'Открыт',
            'closed' => 'Закрыт',
        ],
    ],
    'flash' => [
        'opened' => 'Заказ открыт.',
        'waiter_assigned' => 'Официант назначен.',
        'subtable_added' => 'Подстол добавлен.',
        'cancelled' => 'Заказ отменен.',
    ],
];
