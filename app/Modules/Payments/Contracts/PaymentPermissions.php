<?php

declare(strict_types=1);

namespace App\Modules\Payments\Contracts;

final class PaymentPermissions
{
    public const string CAPTURE = 'payments.capture';

    public const string MANAGE_CASHBOXES = 'payments.cashboxes.manage';
}
