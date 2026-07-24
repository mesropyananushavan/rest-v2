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

final class SetTenantTranslationOverride
{
    use RecordsTenantTranslationOverrideAction;

    public function __construct(
        private readonly TenantResolver $tenants,
        private readonly Authorizer $authorizer,
        private readonly LanguageFileTranslationKeys $languageKeys,
        private readonly NonOverridableTranslationKeys $nonOverridableKeys,
    ) {}

    public function __invoke(Authenticatable $actor, string $locale, string $key, string $value): TenantTranslationOverride
    {
        $startedAt = microtime(true);
        $tenantId = null;

        try {
            $tenantId = $this->tenantId();
            $this->authorize($actor, $tenantId);
            $this->validateWrite($locale, $key, $value);
        } catch (TenantTranslationOverrideException $exception) {
            $this->logDomainFailure('tenant_translation_overrides.set', $exception, $startedAt, [
                'locale' => $locale,
                'translation_key' => $key,
            ]);

            throw $exception;
        }

        $override = DB::transaction(function () use ($key, $locale, $value): TenantTranslationOverride {
            $existing = TenantTranslationOverride::query()
                ->where('locale', $locale)
                ->where('translation_key', $key)
                ->first();
            $before = $existing instanceof TenantTranslationOverride
                ? $this->translationOverrideAuditPayload($existing)
                : null;

            $override = $existing instanceof TenantTranslationOverride
                ? tap($existing)->update(['override_value' => $value])
                : TenantTranslationOverride::query()->create([
                    'locale' => $locale,
                    'translation_key' => $key,
                    'override_value' => $value,
                ]);

            $this->auditTranslationOverrideMutation(
                'tenant_translation_override.set',
                (int) $override->id,
                $before,
                $this->translationOverrideAuditPayload($override->refresh()),
            );

            return $override;
        });

        $this->logSuccess('tenant_translation_overrides.set', $startedAt, [
            'tenant_id' => $tenantId,
            'locale' => $locale,
            'translation_key' => $key,
            'override_id' => (int) $override->id,
        ]);

        return $override;
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

    private function validateWrite(string $locale, string $key, string $value): void
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

        if (mb_strlen($value) > TenantTranslationOverrideRules::MAX_VALUE_LENGTH) {
            throw TenantTranslationOverrideException::valueTooLong();
        }
    }
}
