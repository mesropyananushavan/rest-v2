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
            $table->index(
                ['tenant_id', 'parent_id', 'deleted_at', 'sort_order', 'id'],
                'menu_categories_tenant_parent_deleted_sort_id_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('menu_categories', function (Blueprint $table): void {
            $table->dropIndex('menu_categories_tenant_parent_deleted_sort_id_idx');
        });
    }
};
