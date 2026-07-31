<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Tenancy\Application\SuspendOverdueTenantSubscriptions;
use Illuminate\Console\Command;

final class SuspendOverdueTenantSubscriptionsCommand extends Command
{
    protected $signature = 'tenancy:subscriptions:auto-suspend';

    protected $description = 'Automatically suspend active tenants whose subscription grace period has elapsed.';

    public function handle(SuspendOverdueTenantSubscriptions $suspendOverdueTenantSubscriptions): int
    {
        $result = $suspendOverdueTenantSubscriptions();

        if (! $result->quietHourReached) {
            $this->line('Automatic tenant suspension skipped before quiet hour.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Automatic tenant suspension complete: candidates=%d suspended=%d skipped_not_serviceable=%d skipped_no_longer_suspendable=%d skipped_already_suspended=%d skipped_unknown_tenant=%d.',
            $result->candidateCount,
            $result->suspendedCount,
            $result->skippedNotServiceableCount,
            $result->skippedNoLongerSuspendableCount,
            $result->skippedAlreadySuspendedCount,
            $result->skippedUnknownTenantCount,
        ));

        return self::SUCCESS;
    }
}
