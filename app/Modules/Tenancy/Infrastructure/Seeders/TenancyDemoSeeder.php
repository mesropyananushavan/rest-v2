<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Infrastructure\Seeders;

use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Modules\Tenancy\Infrastructure\Models\Branch;
use App\Modules\Tenancy\Infrastructure\Models\Tenant;
use App\Modules\Tenancy\Infrastructure\Models\TenantSubscription;
use Illuminate\Database\Seeder;

final class TenancyDemoSeeder extends Seeder
{
    /**
     * @return array{tenants: array<string, int>, branches: array<string, int>}
     */
    public function seed(): array
    {
        $tenantResolver = app(TenantResolver::class);
        $branchContext = app(BranchContext::class);

        $tenants = [];
        $branches = [];

        foreach ($this->tenantRows() as $tenantRow) {
            $tenant = Tenant::query()->updateOrCreate(
                ['slug' => $tenantRow['slug']],
                [
                    'name' => $tenantRow['name'],
                    'default_locale' => $tenantRow['default_locale'],
                    'currency' => $tenantRow['currency'],
                    'status' => 'active',
                ],
            );

            $tenants[$tenantRow['slug']] = (int) $tenant->id;
            $tenantResolver->set((int) $tenant->id);

            $this->seedSubscription((int) $tenant->id, $tenantRow['subscription']);

            foreach ($tenantRow['branches'] as $branchRow) {
                $branch = Branch::query()->updateOrCreate(
                    [
                        'tenant_id' => (int) $tenant->id,
                        'name' => $branchRow['name'],
                    ],
                    [
                        'address' => $branchRow['address'],
                        'phone' => $branchRow['phone'],
                        'locale' => $branchRow['locale'],
                        'timezone' => $branchRow['timezone'],
                        'status' => 'active',
                    ],
                );

                $branches[$branchRow['key']] = (int) $branch->id;
            }
        }

        $branchContext->clear();
        $tenantResolver->clear();

        return [
            'tenants' => $tenants,
            'branches' => $branches,
        ];
    }

    /**
     * @param  array{billing_anchor_day: int, next_due_on: string, grace_days_offset: int, last_paid_on: string|null}  $subscriptionRow
     */
    private function seedSubscription(int $tenantId, array $subscriptionRow): void
    {
        $defaultGraceDays = config('billing.default_grace_days');
        assert(is_int($defaultGraceDays));

        TenantSubscription::query()->updateOrCreate(
            ['tenant_id' => $tenantId],
            [
                'billing_anchor_day' => $subscriptionRow['billing_anchor_day'],
                'next_due_on' => $subscriptionRow['next_due_on'],
                'grace_days' => $defaultGraceDays + $subscriptionRow['grace_days_offset'],
                'last_paid_on' => $subscriptionRow['last_paid_on'],
            ],
        );
    }

    /**
     * @return list<array{
     *     slug: string,
     *     name: string,
     *     default_locale: string,
     *     currency: string,
     *     subscription: array{billing_anchor_day: int, next_due_on: string, grace_days_offset: int, last_paid_on: string|null},
     *     branches: list<array{key: string, name: string, address: string, phone: string, locale: string, timezone: string}>
     * }>
     */
    private function tenantRows(): array
    {
        return [
            [
                'slug' => 'arat-riverside',
                'name' => 'Arat Riverside Restaurants',
                'default_locale' => 'hy',
                'currency' => 'AMD',
                'subscription' => [
                    'billing_anchor_day' => 1,
                    'next_due_on' => '2026-08-01',
                    'grace_days_offset' => 0,
                    'last_paid_on' => '2026-07-01',
                ],
                'branches' => [
                    [
                        'key' => 'arat-kentron',
                        'name' => 'Arat Kentron',
                        'address' => '12 Abovyan St, Yerevan',
                        'phone' => '+374 10 111111',
                        'locale' => 'hy',
                        'timezone' => 'Asia/Yerevan',
                    ],
                    [
                        'key' => 'arat-dilijan',
                        'name' => 'Arat Dilijan Terrace',
                        'address' => '5 Myasnikyan St, Dilijan',
                        'phone' => '+374 60 222222',
                        'locale' => 'hy',
                        'timezone' => 'Asia/Yerevan',
                    ],
                ],
            ],
            [
                'slug' => 'northstar-bistro',
                'name' => 'Northstar Bistro Group',
                'default_locale' => 'en',
                'currency' => 'USD',
                'subscription' => [
                    'billing_anchor_day' => 31,
                    'next_due_on' => '2026-08-31',
                    'grace_days_offset' => 2,
                    'last_paid_on' => '2026-07-31',
                ],
                'branches' => [
                    [
                        'key' => 'northstar-downtown',
                        'name' => 'Northstar Downtown',
                        'address' => '44 Market St, Austin',
                        'phone' => '+1 512 555 0144',
                        'locale' => 'en',
                        'timezone' => 'America/Chicago',
                    ],
                ],
            ],
        ];
    }
}
