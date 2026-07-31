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
use Throwable;

final class AuthenticateUser
{
    public function __construct(
        private readonly TenantDirectory $tenants,
        private readonly TenantResolver $tenantResolver,
        private readonly BranchContext $branches,
    ) {}

    public function __invoke(string $tenantSlug, string $email, string $password): ?User
    {
        $normalizedTenantSlug = trim($tenantSlug);
        $normalizedEmail = strtolower(trim($email));

        $this->clearAttemptContext();

        try {
            $tenantId = $this->tenants->serviceableTenantIdForSlug($normalizedTenantSlug);

            if ($tenantId === null) {
                return $this->failedAttempt($normalizedEmail);
            }

            $this->tenantResolver->set($tenantId);
            LogContext::refreshRuntimeContext('identity');

            $user = User::query()
                ->where('email', $normalizedEmail)
                ->where('active', true)
                ->first();

            if (! $user instanceof User || ! Hash::check($password, (string) $user->password)) {
                return $this->failedAttempt($normalizedEmail);
            }

            Auth::login($user);
            LogContext::refreshRuntimeContext('identity');

            Log::info('login success', Redactor::context([
                'auth_event' => 'login_success',
                'user_id' => (int) $user->id,
                'email_hash' => hash('sha256', $normalizedEmail),
            ]));

            return $user;
        } catch (Throwable $exception) {
            $this->clearAttemptContext();

            throw $exception;
        }
    }

    private function failedAttempt(string $normalizedEmail): null
    {
        $this->clearAttemptContext();

        Log::warning('login failure', Redactor::context([
            'auth_event' => 'login_failure',
            'email_hash' => hash('sha256', $normalizedEmail),
        ]));

        return null;
    }

    private function clearAttemptContext(): void
    {
        Auth::logout();
        $this->branches->clear();
        $this->tenantResolver->clear();
        LogContext::refreshRuntimeContext('identity');
    }
}
