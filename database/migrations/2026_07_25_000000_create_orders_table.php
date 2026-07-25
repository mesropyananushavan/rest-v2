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
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32);
            $table->string('status', 32)->default('open');
            $table->foreignId('table_id')->nullable()->constrained('tables')->restrictOnDelete();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->foreignId('waiter_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('cashier_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->unsignedInteger('client_count')->default(1);
            $table->text('comment')->nullable();
            $table->unsignedBigInteger('subtotal_minor')->default(0);
            $table->unsignedBigInteger('discount_minor')->default(0);
            $table->unsignedBigInteger('total_minor')->default(0);
            $table->char('currency', 3);
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('branch_id');
            $table->index('table_id');
            $table->index('customer_id');
            $table->index('waiter_id');
            $table->index('cashier_id');
            $table->index(['tenant_id', 'branch_id', 'status', 'opened_at', 'id'], 'orders_tenant_branch_status_opened_id_idx');
            $table->index(['tenant_id', 'branch_id', 'table_id', 'status'], 'orders_tenant_branch_table_status_idx');
        });

        $this->addPostgresConstraintsAndIndexes();
        $this->enablePostgresTenantPolicy();
    }

    public function down(): void
    {
        $this->dropPostgresTenantPolicy();

        Schema::dropIfExists('orders');
    }

    private function addPostgresConstraintsAndIndexes(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_type_chk CHECK (type IN ('dine_in', 'fast_food', 'takeaway', 'delivery'))");
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_status_chk CHECK (status IN ('open', 'closed', 'cancelled'))");
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_table_type_chk CHECK ((type = 'dine_in' AND table_id IS NOT NULL) OR (type <> 'dine_in' AND table_id IS NULL))");
        DB::statement("CREATE UNIQUE INDEX orders_one_open_dine_in_per_table_idx ON orders (table_id) WHERE status = 'open' AND type = 'dine_in'");
    }

    private function enablePostgresTenantPolicy(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE orders ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE orders FORCE ROW LEVEL SECURITY');
        DB::statement("CREATE POLICY orders_tenant_isolation ON orders USING (tenant_id = nullif(current_setting('smartrest.tenant_id', true), '')::bigint) WITH CHECK (tenant_id = nullif(current_setting('smartrest.tenant_id', true), '')::bigint)");
    }

    private function dropPostgresTenantPolicy(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP POLICY IF EXISTS orders_tenant_isolation ON orders');
    }
};
