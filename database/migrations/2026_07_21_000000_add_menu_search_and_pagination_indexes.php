<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_categories', function (Blueprint $table): void {
            $table->index(['tenant_id', 'deleted_at', 'sort_order', 'id'], 'menu_categories_tenant_deleted_sort_id_idx');
        });

        Schema::table('menu_items', function (Blueprint $table): void {
            $table->index(['tenant_id', 'branch_id', 'category_id', 'deleted_at', 'active', 'sort_order', 'id'], 'menu_items_tenant_branch_category_deleted_active_sort_id_idx');
            $table->index(['tenant_id', 'branch_id', 'category_id', 'deleted_at', 'sort_order', 'id'], 'menu_items_tenant_branch_category_deleted_sort_id_idx');
            $table->index(['tenant_id', 'branch_id', 'deleted_at', 'active', 'sort_order', 'id'], 'menu_items_tenant_branch_deleted_active_sort_id_idx');
        });

        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        if (! $this->postgresExtensionExists('pg_trgm')) {
            DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        }

        DB::statement("CREATE INDEX menu_categories_translated_name_trgm_idx ON menu_categories USING gin ((lower(coalesce(translated_name->>'hy', '') || ' ' || coalesce(translated_name->>'ru', '') || ' ' || coalesce(translated_name->>'en', ''))) gin_trgm_ops)");
        DB::statement("CREATE INDEX menu_items_translated_name_trgm_idx ON menu_items USING gin ((lower(coalesce(translated_name->>'hy', '') || ' ' || coalesce(translated_name->>'ru', '') || ' ' || coalesce(translated_name->>'en', ''))) gin_trgm_ops)");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS menu_items_translated_name_trgm_idx');
            DB::statement('DROP INDEX IF EXISTS menu_categories_translated_name_trgm_idx');
        }

        Schema::table('menu_items', function (Blueprint $table): void {
            $table->dropIndex('menu_items_tenant_branch_deleted_active_sort_id_idx');
            $table->dropIndex('menu_items_tenant_branch_category_deleted_sort_id_idx');
            $table->dropIndex('menu_items_tenant_branch_category_deleted_active_sort_id_idx');
        });

        Schema::table('menu_categories', function (Blueprint $table): void {
            $table->dropIndex('menu_categories_tenant_deleted_sort_id_idx');
        });
    }

    private function postgresExtensionExists(string $extension): bool
    {
        $result = DB::selectOne(
            'select count(*)::int as extension_count from pg_extension where extname = ?',
            [$extension],
        );

        return (int) ($result?->extension_count ?? 0) > 0;
    }
};
