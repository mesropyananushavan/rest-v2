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
            $table->string('load_test_key')->nullable()->after('active');
            $table->index(['tenant_id', 'load_test_key'], 'menu_categories_tenant_load_test_key_idx');
        });

        Schema::table('menu_items', function (Blueprint $table): void {
            $table->string('load_test_key')->nullable()->after('active');
            $table->index(['tenant_id', 'branch_id', 'load_test_key'], 'menu_items_tenant_branch_load_test_key_idx');
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table): void {
            $table->dropIndex('menu_items_tenant_branch_load_test_key_idx');
            $table->dropColumn('load_test_key');
        });

        Schema::table('menu_categories', function (Blueprint $table): void {
            $table->dropIndex('menu_categories_tenant_load_test_key_idx');
            $table->dropColumn('load_test_key');
        });
    }
};
