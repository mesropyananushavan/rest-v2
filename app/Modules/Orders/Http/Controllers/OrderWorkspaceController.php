<?php

declare(strict_types=1);

namespace App\Modules\Orders\Http\Controllers;

use Illuminate\View\View;

final class OrderWorkspaceController
{
    public function __invoke(int $order): View
    {
        return view('modules.orders.workspace', [
            'orderId' => $order,
        ]);
    }
}
