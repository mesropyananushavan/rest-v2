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
        Schema::create('order_moves', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('source_table_id')->constrained('tables')->restrictOnDelete();
            $table->foreignId('target_table_id')->constrained('tables')->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('branch_id');
            $table->index('order_id');
            $table->index('source_table_id');
            $table->index('target_table_id');
            $table->index('actor_id');
            $table->index(['tenant_id', 'branch_id', 'order_id'], 'order_moves_tenant_branch_order_idx');
        });

        $this->enablePostgresTenantPolicy();
    }

    public function down(): void
    {
        $this->dropPostgresTenantPolicy();

        Schema::dropIfExists('order_moves');
    }

    private function enablePostgresTenantPolicy(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE order_moves ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE order_moves FORCE ROW LEVEL SECURITY');
        DB::statement("CREATE POLICY order_moves_tenant_isolation ON order_moves USING (tenant_id = nullif(current_setting('smartrest.tenant_id', true), '')::bigint) WITH CHECK (tenant_id = nullif(current_setting('smartrest.tenant_id', true), '')::bigint)");
    }

    private function dropPostgresTenantPolicy(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP POLICY IF EXISTS order_moves_tenant_isolation ON order_moves');
    }
};
