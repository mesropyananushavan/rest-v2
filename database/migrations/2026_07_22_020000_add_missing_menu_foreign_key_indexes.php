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
            $table->index('parent_id', 'menu_categories_parent_id_idx');
            $table->index('archived_with_category_id', 'menu_categories_archived_with_category_id_idx');
        });

        Schema::table('menu_items', function (Blueprint $table): void {
            $table->index('branch_id', 'menu_items_branch_id_idx');
            $table->index('category_id', 'menu_items_category_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table): void {
            $table->dropIndex('menu_items_category_id_idx');
            $table->dropIndex('menu_items_branch_id_idx');
        });

        Schema::table('menu_categories', function (Blueprint $table): void {
            $table->dropIndex('menu_categories_archived_with_category_id_idx');
            $table->dropIndex('menu_categories_parent_id_idx');
        });
    }
};
