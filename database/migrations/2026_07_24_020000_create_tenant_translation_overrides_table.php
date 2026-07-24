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
        Schema::create('tenant_translation_overrides', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 8);
            $table->string('translation_key');
            $table->text('override_value');
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'locale'], 'tenant_translation_overrides_tenant_locale_idx');
            $table->unique(['tenant_id', 'locale', 'translation_key'], 'tenant_translation_overrides_tenant_locale_key_unique');
        });

        $this->enablePostgresTenantPolicy();
    }

    public function down(): void
    {
        $this->dropPostgresTenantPolicy();

        Schema::dropIfExists('tenant_translation_overrides');
    }

    private function enablePostgresTenantPolicy(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE tenant_translation_overrides ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE tenant_translation_overrides FORCE ROW LEVEL SECURITY');
        DB::statement("CREATE POLICY tenant_translation_overrides_tenant_isolation ON tenant_translation_overrides USING (tenant_id = nullif(current_setting('smartrest.tenant_id', true), '')::bigint) WITH CHECK (tenant_id = nullif(current_setting('smartrest.tenant_id', true), '')::bigint)");
    }

    private function dropPostgresTenantPolicy(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP POLICY IF EXISTS tenant_translation_overrides_tenant_isolation ON tenant_translation_overrides');
    }
};
