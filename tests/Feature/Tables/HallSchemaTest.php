<?php

declare(strict_types=1);

use App\Modules\Tables\Infrastructure\Models\Hall;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('creates the halls schema with tenant branch archive and lookup indexes', function (): void {
    expect(Schema::hasTable('halls'))->toBeTrue()
        ->and(Schema::hasColumns('halls', [
            'id',
            'tenant_id',
            'branch_id',
            'translated_name',
            'color',
            'sort_order',
            'active',
            'deleted_at',
            'created_at',
            'updated_at',
        ]))->toBeTrue()
        ->and(class_uses_recursive(Hall::class))->toContain(SoftDeletes::class);

    $indexNames = collect(Schema::getIndexes('halls'))
        ->pluck('name')
        ->all();

    expect($indexNames)->toContain('halls_tenant_id_index')
        ->and($indexNames)->toContain('halls_branch_id_index')
        ->and($indexNames)->toContain('halls_tenant_branch_deleted_active_sort_id_idx')
        ->and($indexNames)->toContain('halls_tenant_branch_deleted_sort_id_idx');
});
