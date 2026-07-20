<?php

declare(strict_types=1);

return [
    'disk' => env('MENU_IMAGES_DISK', 'public'),
    'path_template' => env('MENU_IMAGES_PATH_TEMPLATE', 'tenants/{tenant_id}/menu/items/{item_id}/{slot}'),
    'placeholder' => 'images/menu-item-placeholder.svg',
    'max_upload_kb' => 4096,
    'allowed_mimes' => [
        'image/jpeg',
        'image/png',
        'image/webp',
    ],
    'allowed_extensions' => [
        'jpg',
        'jpeg',
        'png',
        'webp',
    ],
    'original' => [
        'max_width' => 1600,
        'max_height' => 1200,
        'quality' => 82,
    ],
    'thumbnail' => [
        'width' => 160,
        'height' => 160,
        'quality' => 78,
    ],
];
