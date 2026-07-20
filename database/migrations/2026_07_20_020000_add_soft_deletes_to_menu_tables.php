<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_categories', function (Blueprint $table): void {
            $table->dropIndex('menu_categories_tenant_id_active_sort_order_index');
            $table->softDeletes();
            $table->index(['tenant_id', 'deleted_at', 'active', 'sort_order'], 'menu_categories_tenant_deleted_active_sort_idx');
        });

        Schema::table('menu_items', function (Blueprint $table): void {
            $table->dropIndex('menu_items_tenant_id_branch_id_active_index');
            $table->dropIndex('menu_items_tenant_id_category_id_sort_order_index');
            $table->unsignedBigInteger('archived_with_category_id')->nullable();
            $table->softDeletes();
            $table->index(['tenant_id', 'branch_id', 'deleted_at', 'active'], 'menu_items_tenant_branch_deleted_active_idx');
            $table->index(['tenant_id', 'category_id', 'deleted_at', 'sort_order'], 'menu_items_tenant_category_deleted_sort_idx');
            $table->index(['tenant_id', 'archived_with_category_id', 'deleted_at'], 'menu_items_tenant_archive_marker_deleted_idx');
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table): void {
            $table->dropIndex('menu_items_tenant_branch_deleted_active_idx');
            $table->dropIndex('menu_items_tenant_category_deleted_sort_idx');
            $table->dropIndex('menu_items_tenant_archive_marker_deleted_idx');
            $table->dropColumn(['archived_with_category_id', 'deleted_at']);
            $table->index(['tenant_id', 'branch_id', 'active']);
            $table->index(['tenant_id', 'category_id', 'sort_order']);
        });

        Schema::table('menu_categories', function (Blueprint $table): void {
            $table->dropIndex('menu_categories_tenant_deleted_active_sort_idx');
            $table->dropColumn('deleted_at');
            $table->index(['tenant_id', 'active', 'sort_order']);
        });
    }
};
