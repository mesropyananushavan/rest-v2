<?php

declare(strict_types=1);

use App\Modules\Payments\Infrastructure\Models\Cashbox;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('creates the cashboxes schema with tenant branch lifecycle and invariant indexes', function (): void {
    expect(Schema::hasTable('cashboxes'))->toBeTrue()
        ->and(Schema::hasColumns('cashboxes', [
            'id',
            'tenant_id',
            'branch_id',
            'name',
            'is_active',
            'is_default',
            'created_at',
            'updated_at',
        ]))->toBeTrue()
        ->and(class_uses_recursive(Cashbox::class))->not->toContain(SoftDeletes::class);

    $indexNames = collect(Schema::getIndexes('cashboxes'))
        ->pluck('name')
        ->all();

    expect($indexNames)->toContain('cashboxes_tenant_id_index')
        ->and($indexNames)->toContain('cashboxes_branch_id_index')
        ->and($indexNames)->toContain('cashboxes_tenant_branch_active_default_id_idx')
        ->and($indexNames)->toContain('cashboxes_tenant_branch_active_name_id_idx')
        ->and($indexNames)->toContain('cashboxes_active_name_unique_idx')
        ->and($indexNames)->toContain('cashboxes_active_default_unique_idx');
});
