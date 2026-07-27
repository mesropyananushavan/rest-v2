<?php

declare(strict_types=1);

use App\Modules\Tables\Infrastructure\Models\Hall;
use App\Modules\Tables\Infrastructure\Models\Table;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

afterEach(function (): void {
    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();
});

it('seeds deterministic halls and tables visible to demo managers by tenant branch and hall', function (): void {
    Storage::fake('public');

    $this->seed(DemoSeeder::class);

    $this->withSession(['_token' => tablesDemoCsrfToken()])
        ->post(route('login.store'), tablesDemoLoginPayload('manager@arat.test'))
        ->assertRedirect(route('admin.dashboard'));

    $this->get(route('admin.tables.halls.index'))
        ->assertOk()
        ->assertSee('Գլխավոր սրահ', false)
        ->assertSee('VIP սրահ', false)
        ->assertSee('Տեռաս', false)
        ->assertDontSee('Forest Hall', false)
        ->assertDontSee('Main Room', false);

    $aratHall = Hall::query()
        ->whereJsonContains('translated_name->en', 'Main Hall')
        ->firstOrFail();

    $northstarPatioTable = Table::withoutGlobalScopes()
        ->whereJsonContains('translated_name->en', 'P1')
        ->firstOrFail();
    $expectedAratTableRecords = tablesDemoTableRecords(
        Table::withoutGlobalScopes()
            ->where('tenant_id', (int) $aratHall->tenant_id)
            ->where('branch_id', (int) $aratHall->branch_id)
            ->where('hall_id', (int) $aratHall->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(),
    );

    $this->get(route('admin.tables.tables.index', ['hall' => (int) $aratHall->id]))
        ->assertOk()
        ->assertSee('VIP', false)
        ->assertSee('Քառակուսի', false)
        ->assertViewHas('tables', function (
            LengthAwarePaginator $tables,
        ) use ($aratHall, $expectedAratTableRecords, $northstarPatioTable): bool {
            $renderedTableRecords = tablesDemoTableRecords($tables->getCollection());

            if ($renderedTableRecords !== $expectedAratTableRecords) {
                return false;
            }

            foreach ($renderedTableRecords as $table) {
                if ($table['id'] === (int) $northstarPatioTable->id
                    || $table['tenant_id'] !== (int) $aratHall->tenant_id
                    || $table['branch_id'] !== (int) $aratHall->branch_id
                    || $table['hall_id'] !== (int) $aratHall->id
                    || in_array('P1', $table['name'], true)) {
                    return false;
                }
            }

            return true;
        });

    $this->withSession(['_token' => tablesDemoCsrfToken()])
        ->post(route('logout'), ['_token' => tablesDemoCsrfToken()])
        ->assertRedirect('/');

    $this->withSession(['_token' => tablesDemoCsrfToken()])
        ->post(route('login.store'), tablesDemoLoginPayload('manager@northstar.test'))
        ->assertRedirect(route('admin.dashboard'));

    $this->get(route('admin.tables.halls.index'))
        ->assertOk()
        ->assertSee('Main Room', false)
        ->assertSee('Patio', false)
        ->assertDontSee('Գլխավոր սրահ', false);

    $northstarHall = Hall::query()
        ->whereJsonContains('translated_name->en', 'Main Room')
        ->firstOrFail();

    $this->get(route('admin.tables.tables.index', ['hall' => (int) $northstarHall->id]))
        ->assertOk()
        ->assertSee('VIP', false)
        ->assertDontSee('Գլխավոր սրահ', false);

    expect(Hall::query()->count())->toBe(2)
        ->and(Table::query()->count())->toBe(5);
});

function tablesDemoCsrfToken(): string
{
    return 'tables-demo-test-token';
}

/**
 * @param  iterable<int, Table>  $tables
 * @return list<array{
 *     id: int,
 *     tenant_id: int,
 *     branch_id: int,
 *     hall_id: int,
 *     name: array{hy: string, ru: string, en: string}
 * }>
 */
function tablesDemoTableRecords(iterable $tables): array
{
    return collect($tables)
        ->map(fn (Table $table): array => [
            'id' => (int) $table->id,
            'tenant_id' => (int) $table->tenant_id,
            'branch_id' => (int) $table->branch_id,
            'hall_id' => (int) $table->hall_id,
            'name' => [
                'hy' => $table->translatedName()->forLocale('hy', 'en'),
                'ru' => $table->translatedName()->forLocale('ru', 'en'),
                'en' => $table->translatedName()->forLocale('en', 'en'),
            ],
        ])
        ->values()
        ->all();
}

/**
 * @return array<string, string>
 */
function tablesDemoLoginPayload(string $email): array
{
    return [
        '_token' => tablesDemoCsrfToken(),
        'email' => $email,
        'password' => 'password',
    ];
}
