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
        Schema::create('tables', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hall_id')->constrained('halls')->restrictOnDelete();
            $table->foreignId('archived_with_hall_id')->nullable()->constrained('halls')->restrictOnDelete();
            $table->json('translated_name');
            $table->string('type', 32)->default('standard');
            $table->string('shape', 32)->default('square');
            $table->unsignedSmallInteger('hdm_department')->nullable();
            $table->boolean('is_delivery')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->softDeletes();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('branch_id');
            $table->index('hall_id');
            $table->index('archived_with_hall_id', 'tables_archived_with_hall_id_idx');
            $table->index(['tenant_id', 'branch_id', 'hall_id', 'deleted_at', 'active', 'sort_order', 'id'], 'tables_tenant_branch_hall_deleted_active_sort_id_idx');
            $table->index(['tenant_id', 'branch_id', 'hall_id', 'deleted_at', 'sort_order', 'id'], 'tables_tenant_branch_hall_deleted_sort_id_idx');
            $table->index(['tenant_id', 'archived_with_hall_id', 'deleted_at'], 'tables_tenant_archive_marker_deleted_idx');
        });

        $this->addCheckConstraints();
        $this->enablePostgresTenantPolicy();
    }

    public function down(): void
    {
        $this->dropPostgresTenantPolicy();

        Schema::dropIfExists('tables');
    }

    private function addCheckConstraints(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("ALTER TABLE tables ADD CONSTRAINT tables_type_chk CHECK (type IN ('standard', 'vip'))");
        DB::statement("ALTER TABLE tables ADD CONSTRAINT tables_shape_chk CHECK (shape IN ('circle', 'square', 'rectangle'))");
    }

    private function enablePostgresTenantPolicy(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE tables ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE tables FORCE ROW LEVEL SECURITY');
        DB::statement("CREATE POLICY tables_tenant_isolation ON tables USING (tenant_id = nullif(current_setting('smartrest.tenant_id', true), '')::bigint) WITH CHECK (tenant_id = nullif(current_setting('smartrest.tenant_id', true), '')::bigint)");
    }

    private function dropPostgresTenantPolicy(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP POLICY IF EXISTS tables_tenant_isolation ON tables');
    }
};
