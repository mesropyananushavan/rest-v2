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
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('action', 128);
            $table->string('target_type', 128);
            $table->unsignedBigInteger('target_id');
            $table->json('before_json')->nullable();
            $table->json('after_json')->nullable();
            $table->string('correlation_id', 128);
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'created_at'], 'audit_logs_tenant_created_at_idx');
            $table->index(['tenant_id', 'action', 'created_at'], 'audit_logs_tenant_action_created_at_idx');
            $table->index(['tenant_id', 'target_type', 'target_id'], 'audit_logs_tenant_target_idx');
            $table->index(['tenant_id', 'branch_id', 'created_at'], 'audit_logs_tenant_branch_created_at_idx');
        });

        $this->enablePostgresTenantPolicy();
        $this->createAppendOnlyTriggers();
    }

    public function down(): void
    {
        $this->dropAppendOnlyTriggers();
        $this->dropPostgresTenantPolicy();

        Schema::dropIfExists('audit_logs');
    }

    private function enablePostgresTenantPolicy(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE audit_logs ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE audit_logs FORCE ROW LEVEL SECURITY');
        DB::statement("CREATE POLICY audit_logs_tenant_isolation ON audit_logs USING (tenant_id = nullif(current_setting('smartrest.tenant_id', true), '')::bigint) WITH CHECK (tenant_id = nullif(current_setting('smartrest.tenant_id', true), '')::bigint)");
    }

    private function dropPostgresTenantPolicy(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP POLICY IF EXISTS audit_logs_tenant_isolation ON audit_logs');
    }

    private function createAppendOnlyTriggers(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement(<<<'SQL'
CREATE OR REPLACE FUNCTION prevent_audit_logs_mutation()
RETURNS trigger AS $$
BEGIN
    RAISE EXCEPTION 'audit_logs are append-only';
END;
$$ LANGUAGE plpgsql
SQL);
            DB::statement('CREATE TRIGGER audit_logs_no_update BEFORE UPDATE ON audit_logs FOR EACH ROW EXECUTE FUNCTION prevent_audit_logs_mutation()');
            DB::statement('CREATE TRIGGER audit_logs_no_delete BEFORE DELETE ON audit_logs FOR EACH ROW EXECUTE FUNCTION prevent_audit_logs_mutation()');

            return;
        }

        if ($driver === 'sqlite') {
            DB::statement("CREATE TRIGGER audit_logs_no_update BEFORE UPDATE ON audit_logs BEGIN SELECT RAISE(ABORT, 'audit_logs are append-only'); END");
            DB::statement("CREATE TRIGGER audit_logs_no_delete BEFORE DELETE ON audit_logs BEGIN SELECT RAISE(ABORT, 'audit_logs are append-only'); END");
        }
    }

    private function dropAppendOnlyTriggers(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('DROP TRIGGER IF EXISTS audit_logs_no_update ON audit_logs');
            DB::statement('DROP TRIGGER IF EXISTS audit_logs_no_delete ON audit_logs');
            DB::statement('DROP FUNCTION IF EXISTS prevent_audit_logs_mutation()');

            return;
        }

        if ($driver === 'sqlite') {
            DB::statement('DROP TRIGGER IF EXISTS audit_logs_no_update');
            DB::statement('DROP TRIGGER IF EXISTS audit_logs_no_delete');
        }
    }
};
