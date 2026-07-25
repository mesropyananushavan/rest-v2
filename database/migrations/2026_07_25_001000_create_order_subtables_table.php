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
        Schema::create('order_subtables', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('name');
            $table->string('status', 32)->default('open');
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('branch_id');
            $table->index('order_id');
            $table->index(['tenant_id', 'branch_id', 'order_id', 'status'], 'order_subtables_tenant_branch_order_status_idx');
        });

        $this->addPostgresConstraints();
        $this->enablePostgresTenantPolicy();
    }

    public function down(): void
    {
        $this->dropPostgresTenantPolicy();

        Schema::dropIfExists('order_subtables');
    }

    private function addPostgresConstraints(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("ALTER TABLE order_subtables ADD CONSTRAINT order_subtables_status_chk CHECK (status IN ('open', 'closed'))");
    }

    private function enablePostgresTenantPolicy(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE order_subtables ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE order_subtables FORCE ROW LEVEL SECURITY');
        DB::statement("CREATE POLICY order_subtables_tenant_isolation ON order_subtables USING (tenant_id = nullif(current_setting('smartrest.tenant_id', true), '')::bigint) WITH CHECK (tenant_id = nullif(current_setting('smartrest.tenant_id', true), '')::bigint)");
    }

    private function dropPostgresTenantPolicy(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP POLICY IF EXISTS order_subtables_tenant_isolation ON order_subtables');
    }
};
