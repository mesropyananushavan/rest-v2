<?php

declare(strict_types=1);

return [
    'platform_timezone' => env('BILLING_PLATFORM_TIMEZONE', 'Asia/Yerevan'),
    'default_grace_days' => (int) env('BILLING_DEFAULT_GRACE_DAYS', 3),
];
