<?php

declare(strict_types=1);

namespace App\Modules\Orders\Http\Controllers;

use Illuminate\View\View;

final class OrderBoardController
{
    public function __invoke(): View
    {
        return view('modules.orders.board');
    }
}
