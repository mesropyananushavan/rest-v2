<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_branch_assignments', function (Blueprint $table): void {
            $table->index(['tenant_id', 'branch_id', 'user_id'], 'user_branch_assignments_tenant_branch_user_idx');
        });
    }

    public function down(): void
    {
        Schema::table('user_branch_assignments', function (Blueprint $table): void {
            $table->dropIndex('user_branch_assignments_tenant_branch_user_idx');
        });
    }
};
