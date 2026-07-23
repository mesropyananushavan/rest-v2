<?php

declare(strict_types=1);

return [
    'errors' => [
        'forbidden' => 'У вас нет прав для выполнения этого действия.',
        'not_found' => 'Запрошенный ресурс не найден.',
        'unauthenticated' => 'Требуется вход в систему.',
        'validation' => 'Переданные параметры запроса недействительны.',
    ],
    'fields' => [
        'category_id' => 'категория',
        'page' => 'страница',
        'per_page' => 'позиций на странице',
        'search' => 'поиск',
    ],
    'validation' => [
        'integer' => 'Поле :attribute должно быть целым числом.',
        'max' => 'Поле :attribute не может быть длиннее :max символов.',
        'min' => 'Поле :attribute должно быть не меньше :min.',
        'string' => 'Поле :attribute должно быть текстом.',
    ],
];
