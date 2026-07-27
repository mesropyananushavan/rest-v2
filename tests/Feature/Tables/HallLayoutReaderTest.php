<?php

declare(strict_types=1);

use App\Modules\Tables\Contracts\HallLayout;
use App\Modules\Tables\Contracts\HallLayoutReader;
use App\Modules\Tables\Contracts\TableLayout;
use App\Modules\Tables\Infrastructure\Models\Hall;
use App\Modules\Tables\Infrastructure\Models\Table;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Modules\Tenancy\Infrastructure\Models\Branch;
use App\Modules\Tenancy\Infrastructure\Models\Tenant;
use App\Support\I18n\LocalizedText;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

afterEach(function (): void {
    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();
});

it('returns active hall layouts with ordered active table layouts for a branch', function (): void {
    $record = hallLayoutTenant('tenant-a');

    hallLayoutContext($record, 0);

    $lateHall = hallLayoutHall($record['branches'][0], 'Late Hall', sortOrder: 20, color: '#D36B5F');
    $firstHall = hallLayoutHall($record['branches'][0], 'First Hall', sortOrder: 10, color: '#5FA8D3');
    $secondHall = hallLayoutHall($record['branches'][0], 'Second Hall', sortOrder: 10, color: '#78CD51');

    $lateTable = hallLayoutTable($firstHall, 'Late Table', sortOrder: 20, type: 'vip', shape: 'rectangle');
    $firstTable = hallLayoutTable($firstHall, 'First Table', sortOrder: 10, type: 'standard', shape: 'square');
    $secondTable = hallLayoutTable($firstHall, 'Second Table', sortOrder: 10, type: 'standard', shape: 'circle');
    hallLayoutTable($secondHall, 'Second Hall Table', sortOrder: 10);

    $layout = app(HallLayoutReader::class)->layoutForBranch((int) $record['branches'][0]->id);

    expect($layout)->toHaveCount(3)
        ->and($layout[0])->toBeInstanceOf(HallLayout::class)
        ->and($layout[0])->not->toBeInstanceOf(Model::class)
        ->and(array_map(fn (HallLayout $hall): int => $hall->id, $layout))->toBe([
            (int) $firstHall->id,
            (int) $secondHall->id,
            (int) $lateHall->id,
        ])
        ->and($layout[0]->branchId)->toBe((int) $record['branches'][0]->id)
        ->and($layout[0]->name)->toBeInstanceOf(LocalizedText::class)
        ->and($layout[0]->name->forLocale('en'))->toBe('First Hall')
        ->and($layout[0]->color)->toBe('#5FA8D3')
        ->and($layout[0]->sortOrder)->toBe(10)
        ->and($layout[0]->tables)->toHaveCount(3)
        ->and($layout[0]->tables[0])->toBeInstanceOf(TableLayout::class)
        ->and($layout[0]->tables[0])->not->toBeInstanceOf(Model::class)
        ->and(array_map(fn (TableLayout $table): int => $table->id, $layout[0]->tables))->toBe([
            (int) $firstTable->id,
            (int) $secondTable->id,
            (int) $lateTable->id,
        ])
        ->and($layout[0]->tables[0]->branchId)->toBe((int) $record['branches'][0]->id)
        ->and($layout[0]->tables[0]->hallId)->toBe((int) $firstHall->id)
        ->and($layout[0]->tables[0]->name)->toBeInstanceOf(LocalizedText::class)
        ->and($layout[0]->tables[0]->name->forLocale('en'))->toBe('First Table')
        ->and($layout[0]->tables[0]->type)->toBe('standard')
        ->and($layout[0]->tables[0]->shape)->toBe('square')
        ->and($layout[0]->tables[0]->sortOrder)->toBe(10);
});

it('excludes inactive trashed and other branch halls and tables', function (): void {
    $record = hallLayoutTenant('tenant-a', branchCount: 2);

    hallLayoutContext($record, 0);

    $visibleHall = hallLayoutHall($record['branches'][0], 'Visible Hall');
    $visibleTable = hallLayoutTable($visibleHall, 'Visible Table');
    $inactiveHall = hallLayoutHall($record['branches'][0], 'Inactive Hall', active: false);
    hallLayoutTable($inactiveHall, 'Inactive Hall Table');
    $trashedHall = hallLayoutHall($record['branches'][0], 'Trashed Hall');
    hallLayoutTable($trashedHall, 'Trashed Hall Table');
    $trashedHall->delete();
    hallLayoutTable($visibleHall, 'Inactive Table', active: false);
    $trashedTable = hallLayoutTable($visibleHall, 'Trashed Table');
    $trashedTable->delete();

    hallLayoutContext($record, 1);

    $otherBranchHall = hallLayoutHall($record['branches'][1], 'Other Branch Hall');
    hallLayoutTable($otherBranchHall, 'Other Branch Table');

    hallLayoutContext($record, 0);

    $layout = app(HallLayoutReader::class)->layoutForBranch((int) $record['branches'][0]->id);

    expect($layout)->toHaveCount(1)
        ->and($layout[0]->id)->toBe((int) $visibleHall->id)
        ->and($layout[0]->tables)->toHaveCount(1)
        ->and($layout[0]->tables[0]->id)->toBe((int) $visibleTable->id);
});

it('keeps hall layouts tenant scoped even when another tenant branch id is requested', function (): void {
    $tenantA = hallLayoutTenant('tenant-a');
    $tenantB = hallLayoutTenant('tenant-b');

    hallLayoutContext($tenantA, 0);
    $tenantAHall = hallLayoutHall($tenantA['branches'][0], 'Tenant A Hall');
    hallLayoutTable($tenantAHall, 'Tenant A Table');

    hallLayoutContext($tenantB, 0);
    $tenantBHall = hallLayoutHall($tenantB['branches'][0], 'Tenant B Hall');
    hallLayoutTable($tenantBHall, 'Tenant B Table');

    hallLayoutContext($tenantA, 0);

    expect(app(HallLayoutReader::class)->layoutForBranch((int) $tenantB['branches'][0]->id))->toBe([])
        ->and(app(HallLayoutReader::class)->layoutForBranch((int) $tenantA['branches'][0]->id)[0]->id)->toBe((int) $tenantAHall->id);

    hallLayoutContext($tenantB, 0);

    expect(app(HallLayoutReader::class)->layoutForBranch((int) $tenantB['branches'][0]->id)[0]->id)->toBe((int) $tenantBHall->id);
});

it('eager loads tables without an n plus one query pattern', function (): void {
    $record = hallLayoutTenant('tenant-a');

    hallLayoutContext($record, 0);

    for ($hallIndex = 1; $hallIndex <= 4; $hallIndex++) {
        $hall = hallLayoutHall($record['branches'][0], "Hall {$hallIndex}", sortOrder: $hallIndex);

        for ($tableIndex = 1; $tableIndex <= 3; $tableIndex++) {
            hallLayoutTable($hall, "Hall {$hallIndex} Table {$tableIndex}", sortOrder: $tableIndex);
        }
    }

    DB::flushQueryLog();
    DB::enableQueryLog();

    try {
        $layout = app(HallLayoutReader::class)->layoutForBranch((int) $record['branches'][0]->id);

        foreach ($layout as $hall) {
            $hall->name->forLocale('en');

            foreach ($hall->tables as $table) {
                $table->name->forLocale('en');
            }
        }

        $queryCount = count(DB::getQueryLog());
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }

    expect($layout)->toHaveCount(4)
        ->and($queryCount)->toBe(2);
});

/**
 * @return array{tenant: Tenant, branches: list<Branch>}
 */
function hallLayoutTenant(string $tenantSlug, int $branchCount = 1): array
{
    $tenant = Tenant::query()->create([
        'name' => str($tenantSlug)->headline()->toString(),
        'slug' => $tenantSlug,
        'default_locale' => 'en',
        'currency' => 'AMD',
        'status' => 'active',
    ]);

    app(TenantResolver::class)->set((int) $tenant->id);

    $branches = [];
    for ($index = 1; $index <= $branchCount; $index++) {
        $branches[] = Branch::query()->create([
            'name' => "{$tenantSlug} Branch {$index}",
            'timezone' => 'Asia/Yerevan',
            'status' => 'active',
        ]);
    }

    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();

    return [
        'tenant' => $tenant,
        'branches' => $branches,
    ];
}

/**
 * @param  array{tenant: Tenant, branches: list<Branch>}  $record
 */
function hallLayoutContext(array $record, int $branchIndex): void
{
    app(TenantResolver::class)->set((int) $record['tenant']->id);
    app(BranchContext::class)->set((int) $record['branches'][$branchIndex]->id);
}

function hallLayoutHall(Branch $branch, string $name, int $sortOrder = 0, string $color = '#5FA8D3', bool $active = true): Hall
{
    return Hall::query()->create([
        'branch_id' => (int) $branch->id,
        'translated_name' => hallLayoutTranslations($name),
        'color' => $color,
        'sort_order' => $sortOrder,
        'active' => $active,
    ]);
}

function hallLayoutTable(
    Hall $hall,
    string $name,
    int $sortOrder = 0,
    string $type = 'standard',
    string $shape = 'square',
    bool $active = true,
): Table {
    return Table::query()->create([
        'branch_id' => (int) $hall->branch_id,
        'hall_id' => (int) $hall->id,
        'translated_name' => hallLayoutTranslations($name),
        'type' => $type,
        'shape' => $shape,
        'sort_order' => $sortOrder,
        'active' => $active,
    ]);
}

/**
 * @return array{hy: string, ru: string, en: string}
 */
function hallLayoutTranslations(string $text): array
{
    return [
        'hy' => $text,
        'ru' => $text,
        'en' => $text,
    ];
}
