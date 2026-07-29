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
        Schema::create('tenant_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('billing_anchor_day');
            $table->date('next_due_on');
            $table->unsignedSmallInteger('grace_days')->default(3);
            $table->date('last_paid_on')->nullable();
            $table->timestamps();

            $table->unique('tenant_id');
            $table->index('tenant_id');
            $table->index(['tenant_id', 'next_due_on', 'grace_days'], 'tenant_subscriptions_suspendable_lookup_idx');
        });

        $this->addPostgresConstraints();
        $this->enablePostgresTenantPolicy();
    }

    public function down(): void
    {
        $this->dropPostgresTenantPolicy();

        Schema::dropIfExists('tenant_subscriptions');
    }

    private function addPostgresConstraints(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE tenant_subscriptions ADD CONSTRAINT tenant_subscriptions_billing_anchor_day_chk CHECK (billing_anchor_day BETWEEN 1 AND 31)');
        DB::statement('ALTER TABLE tenant_subscriptions ADD CONSTRAINT tenant_subscriptions_grace_days_chk CHECK (grace_days >= 0)');
    }

    private function enablePostgresTenantPolicy(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE tenant_subscriptions ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE tenant_subscriptions FORCE ROW LEVEL SECURITY');
        DB::statement("CREATE POLICY tenant_subscriptions_tenant_isolation ON tenant_subscriptions USING (tenant_id = nullif(current_setting('smartrest.tenant_id', true), '')::bigint) WITH CHECK (tenant_id = nullif(current_setting('smartrest.tenant_id', true), '')::bigint)");
    }

    private function dropPostgresTenantPolicy(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP POLICY IF EXISTS tenant_subscriptions_tenant_isolation ON tenant_subscriptions');
    }
};
