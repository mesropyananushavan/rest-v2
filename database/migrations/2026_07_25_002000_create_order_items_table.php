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
        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('subtable_id')->nullable()->constrained('order_subtables')->nullOnDelete();
            $table->unsignedBigInteger('menu_item_id');
            $table->unsignedInteger('qty');
            $table->unsignedBigInteger('unit_price_minor');
            $table->unsignedBigInteger('discount_minor')->default(0);
            $table->unsignedBigInteger('total_minor');
            $table->char('currency', 3);
            $table->foreignId('seller_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('preparation_status', 32)->default('pending');
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('branch_id');
            $table->index('order_id');
            $table->index('subtable_id');
            $table->index('menu_item_id');
            $table->index('seller_id');
            $table->index(['tenant_id', 'branch_id', 'order_id', 'preparation_status', 'menu_item_id'], 'order_items_tenant_branch_order_status_item_idx');
            $table->index(['tenant_id', 'branch_id', 'menu_item_id'], 'order_items_tenant_branch_menu_item_idx');
            $table->index(['tenant_id', 'branch_id', 'order_id', 'subtable_id', 'menu_item_id', 'unit_price_minor'], 'order_items_order_subtable_menu_price_idx');
        });

        $this->addPostgresConstraints();
        $this->enablePostgresTenantPolicy();
    }

    public function down(): void
    {
        $this->dropPostgresTenantPolicy();

        Schema::dropIfExists('order_items');
    }

    private function addPostgresConstraints(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_qty_chk CHECK (qty >= 1)');
        DB::statement("ALTER TABLE order_items ADD CONSTRAINT order_items_preparation_status_chk CHECK (preparation_status IN ('pending'))");
    }

    private function enablePostgresTenantPolicy(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE order_items ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE order_items FORCE ROW LEVEL SECURITY');
        DB::statement("CREATE POLICY order_items_tenant_isolation ON order_items USING (tenant_id = nullif(current_setting('smartrest.tenant_id', true), '')::bigint) WITH CHECK (tenant_id = nullif(current_setting('smartrest.tenant_id', true), '')::bigint)");
    }

    private function dropPostgresTenantPolicy(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP POLICY IF EXISTS order_items_tenant_isolation ON order_items');
    }
};
