<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Modules\Orders\Application\ListTableOccupancy;
use App\Modules\Orders\Application\TableOccupancy;
use App\Modules\Tables\Contracts\HallLayout;
use App\Modules\Tables\Contracts\HallLayoutReader;
use App\Modules\Tables\Contracts\TableLayout;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Support\Money\Money;
use App\Support\Money\MoneyFormatter;
use Illuminate\Contracts\View\View;
use Livewire\Component;

final class OrderBoard extends Component
{
    public function render(): View
    {
        return view('livewire.admin.order-board', [
            'halls' => $this->halls(),
        ]);
    }

    /**
     * @return list<array{
     *     id: int,
     *     name: string,
     *     color: string,
     *     sort_order: int,
     *     tables: list<array{
     *         id: int,
     *         name: string,
     *         type: string,
     *         shape: string,
     *         sort_order: int,
     *         occupied: bool,
     *         occupancy: array{
     *             order_id: int,
     *             client_count: int,
     *             opened_at: string,
     *             duration_minutes: int,
     *             total: string
     *         }|null
     *     }>
     * }>
     */
    private function halls(): array
    {
        $branchId = app(BranchContext::class)->id();

        abort_if($branchId === null, 404);

        /** @var list<HallLayout> $layout */
        $layout = app(HallLayoutReader::class)->layoutForBranch($branchId);
        /** @var array<int, TableOccupancy> $occupancy */
        $occupancy = app(ListTableOccupancy::class)();
        $locale = app()->getLocale();

        return array_map(
            fn (HallLayout $hall): array => [
                'id' => $hall->id,
                'name' => $hall->name->forLocale($locale, 'en'),
                'color' => $hall->color,
                'sort_order' => $hall->sortOrder,
                'tables' => array_map(
                    fn (TableLayout $table): array => $this->table($table, $occupancy[$table->id] ?? null, $locale),
                    $hall->tables,
                ),
            ],
            $layout,
        );
    }

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     type: string,
     *     shape: string,
     *     sort_order: int,
     *     occupied: bool,
     *     occupancy: array{
     *         order_id: int,
     *         client_count: int,
     *         opened_at: string,
     *         duration_minutes: int,
     *         total: string
     *     }|null
     * }
     */
    private function table(TableLayout $table, ?TableOccupancy $occupancy, string $locale): array
    {
        return [
            'id' => $table->id,
            'name' => $table->name->forLocale($locale, 'en'),
            'type' => $table->type,
            'shape' => $table->shape,
            'sort_order' => $table->sortOrder,
            'occupied' => $occupancy !== null,
            'occupancy' => $occupancy === null ? null : [
                'order_id' => $occupancy->orderId,
                'client_count' => $occupancy->clientCount,
                'opened_at' => $occupancy->openedAt->format('H:i'),
                'duration_minutes' => $this->durationMinutes($occupancy),
                'total' => MoneyFormatter::format(new Money($occupancy->totalMinor, $occupancy->currency), $locale),
            ],
        ];
    }

    private function durationMinutes(TableOccupancy $occupancy): int
    {
        return max(0, intdiv(time() - $occupancy->openedAt->getTimestamp(), 60));
    }
}
