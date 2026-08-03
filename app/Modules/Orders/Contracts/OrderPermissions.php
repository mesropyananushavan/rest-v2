<?php

declare(strict_types=1);

namespace App\Modules\Orders\Contracts;

final class OrderPermissions
{
    public const string TAKE = 'orders.take';

    public const string CANCEL = 'orders.cancel';
}
