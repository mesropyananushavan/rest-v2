<?php

declare(strict_types=1);

use App\Modules\Tables\Infrastructure\Models\Table;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('creates the tables schema with hall branch archive and lookup indexes', function (): void {
    expect(Schema::hasTable('tables'))->toBeTrue()
        ->and(Schema::hasColumns('tables', [
            'id',
            'tenant_id',
            'branch_id',
            'hall_id',
            'archived_with_hall_id',
            'translated_name',
            'type',
            'shape',
            'hdm_department',
            'is_delivery',
            'sort_order',
            'active',
            'deleted_at',
            'created_at',
            'updated_at',
        ]))->toBeTrue()
        ->and(class_uses_recursive(Table::class))->toContain(SoftDeletes::class);

    $indexNames = collect(Schema::getIndexes('tables'))
        ->pluck('name')
        ->all();

    expect($indexNames)->toContain('tables_tenant_id_index')
        ->and($indexNames)->toContain('tables_branch_id_index')
        ->and($indexNames)->toContain('tables_hall_id_index')
        ->and($indexNames)->toContain('tables_archived_with_hall_id_idx')
        ->and($indexNames)->toContain('tables_tenant_branch_hall_deleted_active_sort_id_idx')
        ->and($indexNames)->toContain('tables_tenant_branch_hall_deleted_sort_id_idx')
        ->and($indexNames)->toContain('tables_tenant_archive_marker_deleted_idx');
});
