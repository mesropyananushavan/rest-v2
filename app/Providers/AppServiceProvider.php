<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\AdminShellComposer;
use App\Modules\Identity\Contracts\Authorizer;
use App\Modules\Identity\Contracts\PermissionCatalog;
use App\Modules\Identity\Contracts\UserDirectory;
use App\Modules\Identity\Infrastructure\Authorization\EloquentAuthorizer;
use App\Modules\Identity\Infrastructure\Authorization\EloquentPermissionCatalog;
use App\Modules\Identity\Infrastructure\Directory\EloquentUserDirectory;
use App\Modules\Menu\Contracts\MenuCatalog;
use App\Modules\Menu\Infrastructure\Catalog\EloquentMenuCatalog;
use App\Modules\Orders\Application\ReadPayableOrder;
use App\Modules\Orders\Contracts\PayableOrderReader;
use App\Modules\Tables\Contracts\HallLayoutReader;
use App\Modules\Tables\Contracts\TableDirectory;
use App\Modules\Tables\Infrastructure\Directory\EloquentHallLayoutReader;
use App\Modules\Tables\Infrastructure\Directory\EloquentTableDirectory;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantDirectory;
use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Modules\Tenancy\Contracts\TenantSettingsReader;
use App\Modules\Tenancy\Contracts\TenantSubscriptionReader;
use App\Modules\Tenancy\Http\Middleware\EnsureTenantIsServiceable;
use App\Modules\Tenancy\Infrastructure\Context\InMemoryBranchContext;
use App\Modules\Tenancy\Infrastructure\Context\InMemoryTenantResolver;
use App\Modules\Tenancy\Infrastructure\Directory\EloquentTenantDirectory;
use App\Modules\Tenancy\Infrastructure\Directory\EloquentTenantSubscriptionReader;
use App\Modules\Tenancy\Infrastructure\Settings\EloquentTenantSettingsReader;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\EloquentAuditRecorder;
use App\Support\I18n\LanguageFileTranslationCatalogue;
use App\Support\I18n\LanguageFileTranslationKeys;
use App\Support\I18n\NonOverridableTranslationKeys;
use App\Support\I18n\TenantAwareTranslator;
use App\Support\I18n\TenantTranslationLocaleFallbacks;
use App\Support\I18n\TenantTranslationOverrides;
use App\Support\Logging\LogContext;
use App\Support\Logging\Redactor;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Translation\Loader;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use UnexpectedValueException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantResolver::class, InMemoryTenantResolver::class);
        $this->app->singleton(BranchContext::class, InMemoryBranchContext::class);
        $this->app->bind(TenantDirectory::class, EloquentTenantDirectory::class);
        $this->app->bind(TenantSettingsReader::class, EloquentTenantSettingsReader::class);
        $this->app->bind(TenantSubscriptionReader::class, function (): EloquentTenantSubscriptionReader {
            $platformTimezone = config('billing.platform_timezone');

            if (! is_string($platformTimezone) || $platformTimezone === '') {
                throw new UnexpectedValueException('Billing platform timezone must be configured.');
            }

            return new EloquentTenantSubscriptionReader($platformTimezone);
        });
        $this->app->bind(Authorizer::class, EloquentAuthorizer::class);
        $this->app->bind(UserDirectory::class, EloquentUserDirectory::class);
        $this->app->bind(PermissionCatalog::class, EloquentPermissionCatalog::class);
        $this->app->bind(MenuCatalog::class, EloquentMenuCatalog::class);
        $this->app->bind(PayableOrderReader::class, ReadPayableOrder::class);
        $this->app->bind(HallLayoutReader::class, EloquentHallLayoutReader::class);
        $this->app->bind(TableDirectory::class, EloquentTableDirectory::class);
        $this->app->bind(AuditRecorder::class, EloquentAuditRecorder::class);
        $this->app->singleton(LanguageFileTranslationKeys::class, function (): LanguageFileTranslationKeys {
            /** @var Loader $loader */
            $loader = $this->app->make('translation.loader');

            return new LanguageFileTranslationKeys($loader);
        });
        $this->app->singleton(LanguageFileTranslationCatalogue::class, function (): LanguageFileTranslationCatalogue {
            /** @var Loader $loader */
            $loader = $this->app->make('translation.loader');

            return new LanguageFileTranslationCatalogue(
                $loader,
                $this->app->make(Filesystem::class),
                lang_path(),
            );
        });
        $this->app->singleton(NonOverridableTranslationKeys::class);
        $this->app->singleton(TenantTranslationLocaleFallbacks::class);
        $this->app->singleton(TenantTranslationOverrides::class);

        $this->app->extend('translator', function (mixed $translator): TenantAwareTranslator {
            /** @var Loader $loader */
            $loader = $this->app->make('translation.loader');

            $tenantAwareTranslator = new TenantAwareTranslator(
                $loader,
                $this->app->getLocale(),
                $this->app->make(TenantTranslationOverrides::class),
                $this->app->make(TenantTranslationLocaleFallbacks::class),
                $this->app->make(NonOverridableTranslationKeys::class),
            );
            $tenantAwareTranslator->setFallback($this->app->getFallbackLocale());

            return $tenantAwareTranslator;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('layouts.admin', AdminShellComposer::class);

        Livewire::addPersistentMiddleware(EnsureTenantIsServiceable::class);

        Queue::createPayloadUsing(fn (): array => [
            'smartrest_context' => LogContext::current(),
        ]);

        Queue::before(function (JobProcessing $event): void {
            $context = $event->job->payload()['smartrest_context'] ?? [];

            if (is_array($context)) {
                /** @var array<string, mixed> $context */
                $this->restoreQueueContext($context);
            }
        });

        Queue::after(function (JobProcessed $event): void {
            $this->clearQueueContext();
        });

        Queue::failing(function (JobFailed $event): void {
            $payload = $event->job->payload();
            $context = $payload['smartrest_context'] ?? [];

            if (is_array($context)) {
                /** @var array<string, mixed> $context */
                $this->restoreQueueContext($context);
            }

            Log::error('queue job failed', Redactor::context([
                'job' => $event->job->resolveName(),
                'connection' => $event->connectionName,
                'queue' => $event->job->getQueue(),
                'exception' => $event->exception::class,
            ]));

            $this->clearQueueContext();
        });

        Gate::before(function (Authenticatable $user, string $ability): ?bool {
            if (! str_contains($ability, '.')) {
                return null;
            }

            return app(Authorizer::class)->allows($user, $ability);
        });
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function restoreQueueContext(array $context): void
    {
        app(TenantResolver::class)->set($this->intOrNull($context['tenant_id'] ?? null));
        app(BranchContext::class)->set($this->intOrNull($context['branch_id'] ?? null));

        LogContext::restore($context);
    }

    private function clearQueueContext(): void
    {
        app(BranchContext::class)->clear();
        app(TenantResolver::class)->clear();

        LogContext::clear();
    }

    private function intOrNull(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
