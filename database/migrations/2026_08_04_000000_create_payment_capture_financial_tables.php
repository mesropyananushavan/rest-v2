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
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->foreignId('cashbox_id')->constrained('cashboxes')->restrictOnDelete();
            $table->string('method', 32);
            $table->string('status', 32);
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('idempotency_key', 128);
            $table->string('idempotency_fingerprint', 64);
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('branch_id');
            $table->index('order_id');
            $table->index('cashbox_id');
            $table->unique(['tenant_id', 'branch_id', 'idempotency_key'], 'payments_tenant_branch_idempotency_key_unique');
            $table->index(['tenant_id', 'branch_id', 'order_id', 'status', 'id'], 'payments_tenant_branch_order_status_id_idx');
            $table->index(['tenant_id', 'branch_id', 'cashbox_id', 'status', 'id'], 'payments_tenant_branch_cashbox_status_id_idx');
        });

        Schema::create('payment_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_id')->constrained('payments')->restrictOnDelete();
            $table->string('payable_type', 64);
            $table->unsignedBigInteger('payable_id');
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('branch_id');
            $table->index('payment_id');
            $table->unique(['payment_id', 'payable_type', 'payable_id'], 'payment_allocations_payment_payable_unique');
            $table->index(['tenant_id', 'branch_id', 'payment_id'], 'payment_allocations_tenant_branch_payment_idx');
            $table->index(['tenant_id', 'branch_id', 'payable_type', 'payable_id'], 'payment_allocations_tenant_branch_payable_idx');
        });

        Schema::create('cashbox_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('cashbox_id')->constrained('cashboxes')->restrictOnDelete();
            $table->string('direction', 16);
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('reason', 64);
            $table->string('source_type', 64);
            $table->unsignedBigInteger('source_id');
            $table->foreignId('posted_by_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('branch_id');
            $table->index('cashbox_id');
            $table->index('posted_by_id');
            $table->unique(['tenant_id', 'branch_id', 'source_type', 'source_id'], 'cashbox_entries_tenant_branch_source_unique');
            $table->index(['tenant_id', 'branch_id', 'cashbox_id', 'created_at', 'id'], 'cashbox_entries_tenant_branch_cashbox_created_id_idx');
            $table->index(['tenant_id', 'branch_id', 'posted_by_id', 'created_at'], 'cashbox_entries_tenant_branch_posted_by_created_idx');
        });

        $this->addDriverSpecificConstraints();
        $this->enablePostgresTenantPolicies();
        $this->createAppendOnlyTriggers();
        $this->createPostgresInsertConsistencyTriggers();
    }

    public function down(): void
    {
        $this->dropPostgresInsertConsistencyTriggers();
        $this->dropAppendOnlyTriggers();
        $this->dropPostgresTenantPolicies();

        Schema::dropIfExists('cashbox_entries');
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('payments');
    }

    private function addDriverSpecificConstraints(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_method_chk CHECK (method IN ('cash'))");
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_status_chk CHECK (status IN ('captured'))");
        DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_amount_positive_chk CHECK (amount_minor > 0)');
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_currency_chk CHECK (currency ~ '^[A-Z]{3}$')");
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_idempotency_key_chk CHECK (length(idempotency_key) BETWEEN 1 AND 128 AND idempotency_key = btrim(idempotency_key) AND idempotency_key !~ '[[:cntrl:]]')");
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_idempotency_fingerprint_chk CHECK (idempotency_fingerprint ~ '^[a-f0-9]{64}$')");

        DB::statement("ALTER TABLE payment_allocations ADD CONSTRAINT payment_allocations_payable_type_chk CHECK (payable_type IN ('order'))");
        DB::statement('ALTER TABLE payment_allocations ADD CONSTRAINT payment_allocations_amount_positive_chk CHECK (amount_minor > 0)');
        DB::statement("ALTER TABLE payment_allocations ADD CONSTRAINT payment_allocations_currency_chk CHECK (currency ~ '^[A-Z]{3}$')");

        DB::statement("ALTER TABLE cashbox_entries ADD CONSTRAINT cashbox_entries_direction_chk CHECK (direction IN ('in'))");
        DB::statement('ALTER TABLE cashbox_entries ADD CONSTRAINT cashbox_entries_amount_positive_chk CHECK (amount_minor > 0)');
        DB::statement("ALTER TABLE cashbox_entries ADD CONSTRAINT cashbox_entries_currency_chk CHECK (currency ~ '^[A-Z]{3}$')");
        DB::statement("ALTER TABLE cashbox_entries ADD CONSTRAINT cashbox_entries_source_type_chk CHECK (source_type IN ('payment'))");
    }

    private function enablePostgresTenantPolicies(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach (['payments', 'payment_allocations', 'cashbox_entries'] as $table) {
            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
            DB::statement("CREATE POLICY {$table}_tenant_isolation ON {$table} USING (tenant_id = nullif(current_setting('smartrest.tenant_id', true), '')::bigint) WITH CHECK (tenant_id = nullif(current_setting('smartrest.tenant_id', true), '')::bigint)");
        }
    }

    private function dropPostgresTenantPolicies(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach (['cashbox_entries', 'payment_allocations', 'payments'] as $table) {
            DB::statement("DROP POLICY IF EXISTS {$table}_tenant_isolation ON {$table}");
        }
    }

    private function createAppendOnlyTriggers(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement(<<<'SQL'
CREATE OR REPLACE FUNCTION prevent_payment_capture_financial_mutation()
RETURNS trigger AS $$
BEGIN
    RAISE EXCEPTION '% are append-only', TG_TABLE_NAME;
END;
$$ LANGUAGE plpgsql
SQL);

            foreach (['payments', 'payment_allocations', 'cashbox_entries'] as $table) {
                DB::statement("CREATE TRIGGER {$table}_no_update BEFORE UPDATE ON {$table} FOR EACH ROW EXECUTE FUNCTION prevent_payment_capture_financial_mutation()");
                DB::statement("CREATE TRIGGER {$table}_no_delete BEFORE DELETE ON {$table} FOR EACH ROW EXECUTE FUNCTION prevent_payment_capture_financial_mutation()");
            }

            return;
        }

        if ($driver === 'sqlite') {
            foreach (['payments', 'payment_allocations', 'cashbox_entries'] as $table) {
                DB::statement("CREATE TRIGGER {$table}_no_update BEFORE UPDATE ON {$table} BEGIN SELECT RAISE(ABORT, '{$table} are append-only'); END");
                DB::statement("CREATE TRIGGER {$table}_no_delete BEFORE DELETE ON {$table} BEGIN SELECT RAISE(ABORT, '{$table} are append-only'); END");
            }
        }
    }

    private function dropAppendOnlyTriggers(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            foreach (['payments', 'payment_allocations', 'cashbox_entries'] as $table) {
                DB::statement("DROP TRIGGER IF EXISTS {$table}_no_update ON {$table}");
                DB::statement("DROP TRIGGER IF EXISTS {$table}_no_delete ON {$table}");
            }

            DB::statement('DROP FUNCTION IF EXISTS prevent_payment_capture_financial_mutation()');

            return;
        }

        if ($driver === 'sqlite') {
            foreach (['payments', 'payment_allocations', 'cashbox_entries'] as $table) {
                DB::statement("DROP TRIGGER IF EXISTS {$table}_no_update");
                DB::statement("DROP TRIGGER IF EXISTS {$table}_no_delete");
            }
        }
    }

    private function createPostgresInsertConsistencyTriggers(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
CREATE OR REPLACE FUNCTION enforce_payment_capture_financial_insert_consistency()
RETURNS trigger AS $$
DECLARE
    payment_row payments%ROWTYPE;
    order_tenant_id bigint;
    order_branch_id bigint;
    order_currency char(3);
    order_total_minor bigint;
    cashbox_tenant_id bigint;
    cashbox_branch_id bigint;
    cashbox_active boolean;
    posted_by_tenant_id bigint;
    allocated_for_payment bigint;
    allocated_for_order bigint;
BEGIN
    IF TG_TABLE_NAME = 'payments' THEN
        SELECT tenant_id, branch_id, currency, total_minor
        INTO order_tenant_id, order_branch_id, order_currency, order_total_minor
        FROM orders
        WHERE id = NEW.order_id;

        IF order_tenant_id IS NULL
            OR order_tenant_id <> NEW.tenant_id
            OR order_branch_id <> NEW.branch_id
            OR order_currency <> NEW.currency THEN
            RAISE EXCEPTION 'payments order tenant branch currency mismatch';
        END IF;

        SELECT tenant_id, branch_id, is_active
        INTO cashbox_tenant_id, cashbox_branch_id, cashbox_active
        FROM cashboxes
        WHERE id = NEW.cashbox_id;

        IF cashbox_tenant_id IS NULL
            OR cashbox_tenant_id <> NEW.tenant_id
            OR cashbox_branch_id <> NEW.branch_id
            OR cashbox_active IS DISTINCT FROM true THEN
            RAISE EXCEPTION 'payments cashbox tenant branch active mismatch';
        END IF;

        RETURN NEW;
    END IF;

    IF TG_TABLE_NAME = 'payment_allocations' THEN
        SELECT *
        INTO payment_row
        FROM payments
        WHERE id = NEW.payment_id;

        IF payment_row.id IS NULL
            OR payment_row.tenant_id <> NEW.tenant_id
            OR payment_row.branch_id <> NEW.branch_id
            OR payment_row.currency <> NEW.currency
            OR payment_row.status <> 'captured' THEN
            RAISE EXCEPTION 'payment_allocations payment tenant branch currency status mismatch';
        END IF;

        SELECT tenant_id, branch_id, currency, total_minor
        INTO order_tenant_id, order_branch_id, order_currency, order_total_minor
        FROM orders
        WHERE id = NEW.payable_id;

        IF order_tenant_id IS NULL
            OR order_tenant_id <> NEW.tenant_id
            OR order_branch_id <> NEW.branch_id
            OR order_currency <> NEW.currency
            OR payment_row.order_id <> NEW.payable_id THEN
            RAISE EXCEPTION 'payment_allocations payable order mismatch';
        END IF;

        SELECT coalesce(sum(amount_minor), 0)
        INTO allocated_for_payment
        FROM payment_allocations
        WHERE payment_id = NEW.payment_id;

        IF allocated_for_payment + NEW.amount_minor > payment_row.amount_minor THEN
            RAISE EXCEPTION 'payment_allocations exceed payment amount';
        END IF;

        SELECT coalesce(sum(payment_allocations.amount_minor), 0)
        INTO allocated_for_order
        FROM payment_allocations
        JOIN payments ON payments.id = payment_allocations.payment_id
        WHERE payment_allocations.tenant_id = NEW.tenant_id
          AND payment_allocations.branch_id = NEW.branch_id
          AND payment_allocations.payable_type = 'order'
          AND payment_allocations.payable_id = NEW.payable_id
          AND payments.status = 'captured';

        IF allocated_for_order + NEW.amount_minor > order_total_minor THEN
            RAISE EXCEPTION 'payment_allocations exceed order total';
        END IF;

        RETURN NEW;
    END IF;

    IF TG_TABLE_NAME = 'cashbox_entries' THEN
        SELECT *
        INTO payment_row
        FROM payments
        WHERE id = NEW.source_id;

        IF payment_row.id IS NULL
            OR payment_row.tenant_id <> NEW.tenant_id
            OR payment_row.branch_id <> NEW.branch_id
            OR payment_row.cashbox_id <> NEW.cashbox_id
            OR payment_row.amount_minor <> NEW.amount_minor
            OR payment_row.currency <> NEW.currency
            OR payment_row.status <> 'captured' THEN
            RAISE EXCEPTION 'cashbox_entries payment source mismatch';
        END IF;

        SELECT tenant_id, branch_id
        INTO cashbox_tenant_id, cashbox_branch_id
        FROM cashboxes
        WHERE id = NEW.cashbox_id;

        IF cashbox_tenant_id IS NULL
            OR cashbox_tenant_id <> NEW.tenant_id
            OR cashbox_branch_id <> NEW.branch_id THEN
            RAISE EXCEPTION 'cashbox_entries cashbox tenant branch mismatch';
        END IF;

        SELECT tenant_id
        INTO posted_by_tenant_id
        FROM users
        WHERE id = NEW.posted_by_id;

        IF posted_by_tenant_id IS NULL OR posted_by_tenant_id <> NEW.tenant_id THEN
            RAISE EXCEPTION 'cashbox_entries posted by tenant mismatch';
        END IF;

        RETURN NEW;
    END IF;

    RAISE EXCEPTION 'unsupported payment capture financial table %', TG_TABLE_NAME;
END;
$$ LANGUAGE plpgsql
SQL);

        DB::statement('CREATE TRIGGER payments_insert_consistency BEFORE INSERT ON payments FOR EACH ROW EXECUTE FUNCTION enforce_payment_capture_financial_insert_consistency()');
        DB::statement('CREATE TRIGGER payment_allocations_insert_consistency BEFORE INSERT ON payment_allocations FOR EACH ROW EXECUTE FUNCTION enforce_payment_capture_financial_insert_consistency()');
        DB::statement('CREATE TRIGGER cashbox_entries_insert_consistency BEFORE INSERT ON cashbox_entries FOR EACH ROW EXECUTE FUNCTION enforce_payment_capture_financial_insert_consistency()');
    }

    private function dropPostgresInsertConsistencyTriggers(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP TRIGGER IF EXISTS cashbox_entries_insert_consistency ON cashbox_entries');
        DB::statement('DROP TRIGGER IF EXISTS payment_allocations_insert_consistency ON payment_allocations');
        DB::statement('DROP TRIGGER IF EXISTS payments_insert_consistency ON payments');
        DB::statement('DROP FUNCTION IF EXISTS enforce_payment_capture_financial_insert_consistency()');
    }
};
