<?php

declare(strict_types=1);

namespace App\Modules\Payments\Infrastructure\Seeders;

use App\Modules\Payments\Infrastructure\Models\Cashbox;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;
use Illuminate\Database\Seeder;
use RuntimeException;

final class PaymentsDemoSeeder extends Seeder
{
    /**
     * @param  array{tenants: array<string, int>, branches: array<string, int>}  $demo
     */
    public function seed(array $demo): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Demo seeders must run only in local or testing environments.');
        }

        $tenants = app(TenantResolver::class);
        $branches = app(BranchContext::class);

        foreach ($this->cashboxRows() as $tenantSlug => $tenantRows) {
            $tenantId = $demo['tenants'][$tenantSlug];
            $tenants->set($tenantId);

            foreach ($tenantRows as $branchKey => $cashboxes) {
                $branchId = $demo['branches'][$branchKey];
                $branches->set($branchId);

                foreach ($cashboxes as $cashboxRow) {
                    Cashbox::query()->updateOrCreate(
                        [
                            'tenant_id' => $tenantId,
                            'branch_id' => $branchId,
                            'name' => $cashboxRow['name'],
                        ],
                        [
                            'is_active' => $cashboxRow['is_active'],
                            'is_default' => $cashboxRow['is_default'],
                        ],
                    );
                }
            }
        }

        $branches->clear();
        $tenants->clear();
    }

    /**
     * @return array<string, array<string, list<array{name: string, is_active: bool, is_default: bool}>>>
     */
    private function cashboxRows(): array
    {
        return [
            'arat-riverside' => [
                'arat-kentron' => [
                    ['name' => 'Main cashbox', 'is_active' => true, 'is_default' => true],
                    ['name' => 'Bar cashbox', 'is_active' => true, 'is_default' => false],
                    ['name' => 'Archive cashbox', 'is_active' => false, 'is_default' => false],
                ],
                'arat-dilijan' => [
                    ['name' => 'Dilijan main cashbox', 'is_active' => true, 'is_default' => true],
                ],
            ],
            'northstar-bistro' => [
                'northstar-downtown' => [
                    ['name' => 'Downtown register', 'is_active' => true, 'is_default' => true],
                    ['name' => 'Patio register', 'is_active' => true, 'is_default' => false],
                ],
            ],
        ];
    }
}
