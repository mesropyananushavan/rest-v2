<?php

declare(strict_types=1);

namespace App\Modules\Tables\Infrastructure\Seeders;

use App\Modules\Tables\Infrastructure\Models\Hall;
use App\Modules\Tables\Infrastructure\Models\Table;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;
use Illuminate\Database\Seeder;
use RuntimeException;

final class TablesDemoSeeder extends Seeder
{
    /**
     * @param  array{tenants: array<string, int>, branches: array<string, int>}  $demo
     */
    public function seed(array $demo): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Demo seeders must run only in local or testing environments.');
        }

        $tenantResolver = app(TenantResolver::class);
        $branchContext = app(BranchContext::class);

        foreach ($this->tenantHalls() as $tenantSlug => $tenantHalls) {
            $tenantId = $demo['tenants'][$tenantSlug];
            $tenantResolver->set($tenantId);

            foreach ($tenantHalls as $branchKey => $halls) {
                $branchId = $demo['branches'][$branchKey];
                $branchContext->set($branchId);

                foreach ($halls as $hallRow) {
                    $hall = Hall::withTrashed()->updateOrCreate(
                        [
                            'tenant_id' => $tenantId,
                            'branch_id' => $branchId,
                            'sort_order' => $hallRow['sort_order'],
                        ],
                        [
                            'translated_name' => $hallRow['name'],
                            'color' => $hallRow['color'],
                            'active' => true,
                        ],
                    );

                    if ($hall->trashed()) {
                        $hall->forceFill(['deleted_at' => null])->save();
                    }

                    $hallKey = $hallRow['key'];

                    foreach ($this->tablesForHall($hallKey) as $tableRow) {
                        $table = Table::withTrashed()->updateOrCreate(
                            [
                                'tenant_id' => $tenantId,
                                'branch_id' => $branchId,
                                'hall_id' => (int) $hall->id,
                                'sort_order' => $tableRow['sort_order'],
                            ],
                            [
                                'translated_name' => $tableRow['name'],
                                'type' => $tableRow['type'],
                                'shape' => $tableRow['shape'],
                                'hdm_department' => $tableRow['hdm_department'],
                                'is_delivery' => $tableRow['is_delivery'],
                                'active' => true,
                                'archived_with_hall_id' => null,
                            ],
                        );

                        if ($table->trashed()) {
                            $table->forceFill(['deleted_at' => null])->save();
                        }
                    }
                }
            }
        }

        $branchContext->clear();
        $tenantResolver->clear();
    }

    /**
     * @return array<string, array<string, list<array{
     *     key: string,
     *     sort_order: int,
     *     color: string,
     *     name: array{hy: string, ru: string, en: string}
     * }>>>
     */
    private function tenantHalls(): array
    {
        return [
            'arat-riverside' => [
                'arat-kentron' => [
                    ['key' => 'arat-kentron-main', 'sort_order' => 10, 'color' => '#5FA8D3', 'name' => $this->localized('Գլխավոր սրահ', 'Главный зал', 'Main Hall')],
                    ['key' => 'arat-kentron-vip', 'sort_order' => 20, 'color' => '#D36B5F', 'name' => $this->localized('VIP սրահ', 'VIP зал', 'VIP Hall')],
                    ['key' => 'arat-kentron-terrace', 'sort_order' => 30, 'color' => '#78CD51', 'name' => $this->localized('Տեռաս', 'Терраса', 'Terrace')],
                ],
                'arat-dilijan' => [
                    ['key' => 'arat-dilijan-forest', 'sort_order' => 10, 'color' => '#8B6FD3', 'name' => $this->localized('Անտառային սրահ', 'Лесной зал', 'Forest Hall')],
                    ['key' => 'arat-dilijan-garden', 'sort_order' => 20, 'color' => '#D3A45F', 'name' => $this->localized('Այգի', 'Сад', 'Garden')],
                ],
            ],
            'northstar-bistro' => [
                'northstar-downtown' => [
                    ['key' => 'northstar-downtown-main', 'sort_order' => 10, 'color' => '#4E8F7A', 'name' => $this->localized('Գլխավոր սրահ', 'Главный зал', 'Main Room')],
                    ['key' => 'northstar-downtown-patio', 'sort_order' => 20, 'color' => '#C96E3F', 'name' => $this->localized('Բակ', 'Патио', 'Patio')],
                ],
            ],
        ];
    }

    /**
     * @return list<array{
     *     sort_order: int,
     *     name: array{hy: string, ru: string, en: string},
     *     type: string,
     *     shape: string,
     *     hdm_department: int|null,
     *     is_delivery: bool
     * }>
     */
    private function tablesForHall(string $hallKey): array
    {
        $rows = [
            'arat-kentron-main' => [
                [10, '1', 'standard', 'square', 1, false],
                [20, '2', 'standard', 'circle', 1, false],
                [30, 'VIP', 'vip', 'rectangle', 1, false],
            ],
            'arat-kentron-vip' => [
                [10, 'V1', 'vip', 'circle', 1, false],
                [20, 'V2', 'vip', 'rectangle', 1, false],
            ],
            'arat-kentron-terrace' => [
                [10, 'T1', 'standard', 'square', 1, false],
                [20, 'Delivery', 'standard', 'rectangle', 1, true],
            ],
            'arat-dilijan-forest' => [
                [10, 'F1', 'standard', 'circle', 1, false],
                [20, 'F2', 'standard', 'square', 1, false],
            ],
            'arat-dilijan-garden' => [
                [10, 'G1', 'standard', 'rectangle', 1, false],
                [20, 'G2', 'standard', 'circle', 1, false],
            ],
            'northstar-downtown-main' => [
                [10, '1', 'standard', 'square', 1, false],
                [20, '2', 'standard', 'circle', 1, false],
                [30, 'VIP', 'vip', 'rectangle', 1, false],
            ],
            'northstar-downtown-patio' => [
                [10, 'P1', 'standard', 'circle', 1, false],
                [20, 'P2', 'standard', 'square', 1, false],
            ],
        ];

        return array_values(array_map(
            fn (array $row): array => [
                'sort_order' => (int) $row[0],
                'name' => $this->localized((string) $row[1], (string) $row[1], (string) $row[1]),
                'type' => (string) $row[2],
                'shape' => (string) $row[3],
                'hdm_department' => $row[4],
                'is_delivery' => (bool) $row[5],
            ],
            $rows[$hallKey] ?? [],
        ));
    }

    /**
     * @return array{hy: string, ru: string, en: string}
     */
    private function localized(string $hy, string $ru, string $en): array
    {
        return [
            'hy' => $hy,
            'ru' => $ru,
            'en' => $en,
        ];
    }
}
