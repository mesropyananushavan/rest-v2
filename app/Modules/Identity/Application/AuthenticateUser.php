<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantDirectory;
use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Support\Logging\LogContext;
use App\Support\Logging\Redactor;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

final class AuthenticateUser
{
    public function __construct(
        private readonly TenantDirectory $tenants,
        private readonly TenantResolver $tenantResolver,
        private readonly BranchContext $branches,
    ) {}

    public function __invoke(string $email, string $password): ?User
    {
        $normalizedEmail = strtolower(trim($email));

        foreach ($this->tenantIdsForAttempt() as $tenantId) {
            $this->tenantResolver->set($tenantId);

            $user = User::query()
                ->where('email', $normalizedEmail)
                ->where('active', true)
                ->first();

            if (! $user instanceof User || ! Hash::check($password, (string) $user->password)) {
                continue;
            }

            Auth::login($user);
            LogContext::refreshRuntimeContext('identity');

            Log::info('login success', Redactor::context([
                'auth_event' => 'login_success',
                'user_id' => (int) $user->id,
                'email_hash' => hash('sha256', $normalizedEmail),
            ]));

            return $user;
        }

        Auth::logout();
        $this->branches->clear();
        $this->tenantResolver->clear();
        LogContext::refreshRuntimeContext('identity');

        Log::warning('login failure', Redactor::context([
            'auth_event' => 'login_failure',
            'email_hash' => hash('sha256', $normalizedEmail),
        ]));

        return null;
    }

    /**
     * @return list<int>
     */
    private function tenantIdsForAttempt(): array
    {
        $currentTenantId = $this->tenantResolver->id();

        if ($currentTenantId !== null) {
            return [$currentTenantId];
        }

        return $this->tenants->activeTenantIds();
    }
}
