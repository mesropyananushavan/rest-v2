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
        Schema::create('cashboxes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('branch_id');
            $table->index(['tenant_id', 'branch_id', 'is_active', 'is_default', 'id'], 'cashboxes_tenant_branch_active_default_id_idx');
            $table->index(['tenant_id', 'branch_id', 'is_active', 'name', 'id'], 'cashboxes_tenant_branch_active_name_id_idx');
        });

        $this->addDriverSpecificConstraints();
        $this->enablePostgresTenantPolicy();
    }

    public function down(): void
    {
        $this->dropPostgresTenantPolicy();

        Schema::dropIfExists('cashboxes');
    }

    private function addDriverSpecificConstraints(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE cashboxes ADD CONSTRAINT cashboxes_default_requires_active_chk CHECK (is_default = false OR is_active = true)');
            DB::statement('CREATE UNIQUE INDEX cashboxes_active_name_unique_idx ON cashboxes (tenant_id, branch_id, lower(name)) WHERE is_active = true');
            DB::statement('CREATE UNIQUE INDEX cashboxes_active_default_unique_idx ON cashboxes (tenant_id, branch_id) WHERE is_active = true AND is_default = true');

            return;
        }

        if ($driver === 'sqlite') {
            DB::statement('CREATE UNIQUE INDEX cashboxes_active_name_unique_idx ON cashboxes (tenant_id, branch_id, lower(name)) WHERE is_active = 1');
            DB::statement('CREATE UNIQUE INDEX cashboxes_active_default_unique_idx ON cashboxes (tenant_id, branch_id) WHERE is_active = 1 AND is_default = 1');
        }
    }

    private function enablePostgresTenantPolicy(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE cashboxes ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE cashboxes FORCE ROW LEVEL SECURITY');
        DB::statement("CREATE POLICY cashboxes_tenant_isolation ON cashboxes USING (tenant_id = nullif(current_setting('smartrest.tenant_id', true), '')::bigint) WITH CHECK (tenant_id = nullif(current_setting('smartrest.tenant_id', true), '')::bigint)");
    }

    private function dropPostgresTenantPolicy(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP POLICY IF EXISTS cashboxes_tenant_isolation ON cashboxes');
    }
};
