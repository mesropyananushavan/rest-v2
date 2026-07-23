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
        Schema::create('halls', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->json('translated_name');
            $table->string('color', 7)->default('#5FA8D3');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->softDeletes();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('branch_id');
            $table->index(['tenant_id', 'branch_id', 'deleted_at', 'active', 'sort_order', 'id'], 'halls_tenant_branch_deleted_active_sort_id_idx');
            $table->index(['tenant_id', 'branch_id', 'deleted_at', 'sort_order', 'id'], 'halls_tenant_branch_deleted_sort_id_idx');
        });

        $this->enablePostgresTenantPolicy();
    }

    public function down(): void
    {
        $this->dropPostgresTenantPolicy();

        Schema::dropIfExists('halls');
    }

    private function enablePostgresTenantPolicy(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE halls ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE halls FORCE ROW LEVEL SECURITY');
        DB::statement("CREATE POLICY halls_tenant_isolation ON halls USING (tenant_id = nullif(current_setting('smartrest.tenant_id', true), '')::bigint) WITH CHECK (tenant_id = nullif(current_setting('smartrest.tenant_id', true), '')::bigint)");
    }

    private function dropPostgresTenantPolicy(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP POLICY IF EXISTS halls_tenant_isolation ON halls');
    }
};
