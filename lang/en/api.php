<?php

declare(strict_types=1);

return [
    'errors' => [
        'forbidden' => 'You do not have permission to perform this action.',
        'not_found' => 'The requested resource was not found.',
        'unauthenticated' => 'Authentication is required.',
        'validation' => 'The submitted query parameters are invalid.',
    ],
    'fields' => [
        'category_id' => 'category',
        'page' => 'page',
        'per_page' => 'items per page',
        'search' => 'search',
    ],
    'validation' => [
        'integer' => 'The :attribute must be an integer.',
        'max' => 'The :attribute may not be greater than :max characters.',
        'min' => 'The :attribute must be at least :min.',
        'string' => 'The :attribute must be text.',
    ],
];
