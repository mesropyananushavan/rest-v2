<?php

declare(strict_types=1);

namespace App\Modules\Tables\Infrastructure\Directory;

use App\Modules\Tables\Contracts\HallLayout;
use App\Modules\Tables\Contracts\HallLayoutReader;
use App\Modules\Tables\Contracts\TableLayout;
use App\Modules\Tables\Infrastructure\Models\Hall;
use App\Modules\Tables\Infrastructure\Models\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;

final class EloquentHallLayoutReader implements HallLayoutReader
{
    public function layoutForBranch(int $branchId): array
    {
        /** @var Collection<int, Hall> $halls */
        $halls = Hall::query()
            ->where('branch_id', $branchId)
            ->where('active', true)
            ->with([
                'tables' => function (Relation $relation) use ($branchId): void {
                    /** @var Builder<Table> $query */
                    $query = $relation->getQuery();
                    $query
                        ->where('branch_id', $branchId)
                        ->where('active', true)
                        ->orderBy('sort_order')
                        ->orderBy('id');
                },
            ])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return array_values($halls
            ->map(fn (Hall $hall): HallLayout => $this->toHallLayout($hall))
            ->values()
            ->all());
    }

    private function toHallLayout(Hall $hall): HallLayout
    {
        return new HallLayout(
            id: (int) $hall->id,
            branchId: (int) $hall->branch_id,
            name: $hall->translatedName(),
            color: (string) $hall->color,
            sortOrder: (int) $hall->sort_order,
            tables: $this->tableLayouts($hall),
        );
    }

    /**
     * @return list<TableLayout>
     */
    private function tableLayouts(Hall $hall): array
    {
        /** @var Collection<int, Table> $tables */
        $tables = $hall->getRelation('tables');

        return array_values($tables
            ->map(fn (Table $table): TableLayout => new TableLayout(
                id: (int) $table->id,
                branchId: (int) $table->branch_id,
                hallId: (int) $table->hall_id,
                name: $table->translatedName(),
                type: (string) $table->type,
                shape: (string) $table->shape,
                sortOrder: (int) $table->sort_order,
            ))
            ->values()
            ->all());
    }
}
