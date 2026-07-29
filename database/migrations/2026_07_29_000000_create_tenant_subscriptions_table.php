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
            $table->index(['next_due_on', 'tenant_id', 'grace_days'], 'tenant_subscriptions_suspendable_lookup_idx');
        });

        $this->addPostgresConstraints();
    }

    public function down(): void
    {
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
};
