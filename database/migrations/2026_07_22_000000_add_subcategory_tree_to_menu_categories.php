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
            $table->foreignId('parent_id')
                ->nullable()
                ->after('tenant_id')
                ->constrained('menu_categories')
                ->restrictOnDelete();
            $table->foreignId('archived_with_category_id')
                ->nullable()
                ->after('parent_id')
                ->constrained('menu_categories')
                ->restrictOnDelete();

            $table->index(
                ['tenant_id', 'parent_id', 'deleted_at', 'active', 'sort_order', 'id'],
                'menu_categories_tenant_parent_deleted_active_sort_id_idx',
            );
            $table->index(
                ['tenant_id', 'archived_with_category_id', 'deleted_at'],
                'menu_categories_tenant_archive_marker_deleted_idx',
            );
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE menu_categories ADD CONSTRAINT menu_categories_parent_not_self_chk CHECK (parent_id IS NULL OR parent_id <> id)');
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE menu_categories DROP CONSTRAINT IF EXISTS menu_categories_parent_not_self_chk');
        }

        Schema::table('menu_categories', function (Blueprint $table): void {
            $table->dropIndex('menu_categories_tenant_archive_marker_deleted_idx');
            $table->dropIndex('menu_categories_tenant_parent_deleted_active_sort_id_idx');
            $table->dropForeign(['archived_with_category_id']);
            $table->dropForeign(['parent_id']);
            $table->dropColumn(['archived_with_category_id', 'parent_id']);
        });
    }
};
