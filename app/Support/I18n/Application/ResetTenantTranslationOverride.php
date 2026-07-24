<?php

declare(strict_types=1);

namespace App\Support\I18n\Application;

use App\Modules\Identity\Contracts\Authorizer;
use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Support\I18n\LanguageFileTranslationKeys;
use App\Support\I18n\NonOverridableTranslationKeys;
use App\Support\I18n\TenantTranslationOverride;
use App\Support\I18n\TenantTranslationOverrideException;
use App\Support\I18n\TenantTranslationOverridePermissions;
use App\Support\I18n\TenantTranslationOverrideRules;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

final class ResetTenantTranslationOverride
{
    use RecordsTenantTranslationOverrideAction;

    public function __construct(
        private readonly TenantResolver $tenants,
        private readonly Authorizer $authorizer,
        private readonly LanguageFileTranslationKeys $languageKeys,
        private readonly NonOverridableTranslationKeys $nonOverridableKeys,
    ) {}

    public function __invoke(Authenticatable $actor, string $locale, string $key): bool
    {
        $startedAt = microtime(true);
        $tenantId = null;

        try {
            $tenantId = $this->tenantId();
            $this->authorize($actor, $tenantId);
            $this->validateReset($locale, $key);
        } catch (TenantTranslationOverrideException $exception) {
            $this->logDomainFailure('tenant_translation_overrides.reset', $exception, $startedAt, [
                'locale' => $locale,
                'translation_key' => $key,
            ]);

            throw $exception;
        }

        $removed = DB::transaction(function () use ($key, $locale): bool {
            $override = TenantTranslationOverride::query()
                ->where('locale', $locale)
                ->where('translation_key', $key)
                ->first();

            if (! $override instanceof TenantTranslationOverride) {
                return false;
            }

            $before = $this->translationOverrideAuditPayload($override);
            $targetId = (int) $override->id;

            $override->delete();

            $this->auditTranslationOverrideMutation(
                'tenant_translation_override.reset',
                $targetId,
                $before,
                ['deleted' => true],
            );

            return true;
        });

        $this->logSuccess('tenant_translation_overrides.reset', $startedAt, [
            'tenant_id' => $tenantId,
            'locale' => $locale,
            'translation_key' => $key,
            'removed' => $removed,
        ]);

        return $removed;
    }

    private function tenantId(): int
    {
        $tenantId = $this->tenants->id();

        if ($tenantId === null) {
            throw TenantTranslationOverrideException::tenantContextRequired();
        }

        return $tenantId;
    }

    private function authorize(Authenticatable $actor, int $tenantId): void
    {
        $actorTenantId = data_get($actor, 'tenant_id');

        if (! is_numeric($actorTenantId) || (int) $actorTenantId !== $tenantId) {
            throw new AuthorizationException;
        }

        if (! $this->authorizer->allows($actor, TenantTranslationOverridePermissions::MANAGE)) {
            throw new AuthorizationException;
        }
    }

    private function validateReset(string $locale, string $key): void
    {
        if (! TenantTranslationOverrideRules::isSupportedLocale($locale)) {
            throw TenantTranslationOverrideException::invalidLocale($locale);
        }

        if (! $this->languageKeys->exists($key)) {
            throw TenantTranslationOverrideException::translationKeyMissing($key);
        }

        if ($this->nonOverridableKeys->contains($key)) {
            throw TenantTranslationOverrideException::keyNotOverridable($key);
        }
    }
}
