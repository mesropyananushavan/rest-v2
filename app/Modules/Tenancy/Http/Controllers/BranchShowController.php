<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers;

use App\Modules\Tenancy\Infrastructure\Models\Branch;
use Illuminate\Http\JsonResponse;

final class BranchShowController
{
    public function __invoke(int $branch): JsonResponse
    {
        $branchModel = Branch::query()->findOrFail($branch);

        return response()->json([
            'data' => [
                'id' => (int) $branchModel->id,
                'name' => (string) $branchModel->name,
            ],
        ]);
    }
}
