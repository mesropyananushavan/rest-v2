<?php

declare(strict_types=1);

namespace App\Modules\Tables\Infrastructure\Seeders;

use App\Modules\Tables\Infrastructure\Models\Hall;
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
                }
            }
        }

        $branchContext->clear();
        $tenantResolver->clear();
    }

    /**
     * @return array<string, array<string, list<array{
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
                    ['sort_order' => 10, 'color' => '#5FA8D3', 'name' => $this->localized('Գլխավոր սրահ', 'Главный зал', 'Main Hall')],
                    ['sort_order' => 20, 'color' => '#D36B5F', 'name' => $this->localized('VIP սրահ', 'VIP зал', 'VIP Hall')],
                    ['sort_order' => 30, 'color' => '#78CD51', 'name' => $this->localized('Տեռաս', 'Терраса', 'Terrace')],
                ],
                'arat-dilijan' => [
                    ['sort_order' => 10, 'color' => '#8B6FD3', 'name' => $this->localized('Անտառային սրահ', 'Лесной зал', 'Forest Hall')],
                    ['sort_order' => 20, 'color' => '#D3A45F', 'name' => $this->localized('Այգի', 'Сад', 'Garden')],
                ],
            ],
            'northstar-bistro' => [
                'northstar-downtown' => [
                    ['sort_order' => 10, 'color' => '#4E8F7A', 'name' => $this->localized('Գլխավոր սրահ', 'Главный зал', 'Main Room')],
                    ['sort_order' => 20, 'color' => '#C96E3F', 'name' => $this->localized('Բակ', 'Патио', 'Patio')],
                ],
            ],
        ];
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
