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
        Schema::create('order_item_moves', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('order_item_id');
            $table->foreignId('source_order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('target_order_id')->constrained('orders')->cascadeOnDelete();
            $table->unsignedBigInteger('source_subtable_id')->nullable();
            $table->unsignedBigInteger('target_subtable_id')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('branch_id');
            $table->index('order_item_id');
            $table->index('source_order_id');
            $table->index('target_order_id');
            $table->index('source_subtable_id');
            $table->index('target_subtable_id');
            $table->index('actor_id');
            $table->index(['tenant_id', 'branch_id', 'order_item_id'], 'order_item_moves_tenant_branch_item_idx');
            $table->index(['tenant_id', 'branch_id', 'source_order_id', 'target_order_id'], 'order_item_moves_tenant_branch_source_target_idx');
        });

        $this->enablePostgresTenantPolicy();
    }

    public function down(): void
    {
        $this->dropPostgresTenantPolicy();

        Schema::dropIfExists('order_item_moves');
    }

    private function enablePostgresTenantPolicy(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE order_item_moves ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE order_item_moves FORCE ROW LEVEL SECURITY');
        DB::statement("CREATE POLICY order_item_moves_tenant_isolation ON order_item_moves USING (tenant_id = nullif(current_setting('smartrest.tenant_id', true), '')::bigint) WITH CHECK (tenant_id = nullif(current_setting('smartrest.tenant_id', true), '')::bigint)");
    }

    private function dropPostgresTenantPolicy(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP POLICY IF EXISTS order_item_moves_tenant_isolation ON order_item_moves');
    }
};
