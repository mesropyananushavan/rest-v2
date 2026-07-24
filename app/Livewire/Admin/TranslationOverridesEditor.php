<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Support\I18n\Application\ResetTenantTranslationOverride;
use App\Support\I18n\Application\SearchTenantTranslationOverrides;
use App\Support\I18n\Application\SetTenantTranslationOverride;
use App\Support\I18n\TenantTranslationOverrideException;
use App\Support\I18n\TenantTranslationOverridePermissions;
use App\Support\I18n\TenantTranslationOverrideRules;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

final class TranslationOverridesEditor extends Component
{
    private const int PAGE_SIZE = 15;

    #[Url(as: 'q', history: true, except: '')]
    public string $search = '';

    #[Url(as: 'locale', history: true, except: 'hy')]
    public string $locale = 'hy';

    #[Url(as: 'page', history: true, except: 1)]
    public int $page = 1;

    public ?string $editingKey = null;

    public string $overrideValue = '';

    public ?string $statusMessage = null;

    public ?string $errorMessage = null;

    public function mount(): void
    {
        $this->normalizeState();
    }

    public function render(SearchTenantTranslationOverrides $searchOverrides): View
    {
        $this->normalizeState();

        return view('livewire.admin.translation-overrides-editor', [
            'locales' => TenantTranslationOverrideRules::supportedLocales(),
            'maxValueLength' => TenantTranslationOverrideRules::MAX_VALUE_LENGTH,
            'rows' => $searchOverrides($this->locale, $this->search, $this->page, self::PAGE_SIZE),
        ]);
    }

    public function startEditing(string $key): void
    {
        $this->authorizeEditor();

        $row = app(SearchTenantTranslationOverrides::class)->rowForKey($this->locale, $key);

        if ($row === null) {
            $this->editingKey = null;
            $this->overrideValue = '';
            $this->errorMessage = __('admin.translation_overrides.errors.translation_key_missing');

            return;
        }

        $this->editingKey = $key;
        $this->overrideValue = $row->effectiveValue;
        $this->resetErrorBag('overrideValue');
    }

    public function cancelEditing(): void
    {
        $this->editingKey = null;
        $this->overrideValue = '';
        $this->resetErrorBag('overrideValue');
    }

    public function save(SetTenantTranslationOverride $setOverride): void
    {
        $this->authorizeEditor();
        $this->statusMessage = null;
        $this->errorMessage = null;

        if ($this->editingKey === null) {
            return;
        }

        $user = auth()->user();
        abort_unless($user !== null, 403);

        try {
            $setOverride($user, $this->locale, $this->editingKey, $this->overrideValue);
        } catch (TenantTranslationOverrideException $exception) {
            $message = __($exception->errorCode());
            $this->errorMessage = $message;
            $this->addError('overrideValue', $message);

            return;
        } catch (AuthorizationException) {
            abort(403);
        }

        $this->editingKey = null;
        $this->overrideValue = '';
        $this->statusMessage = __('admin.translation_overrides.flash.saved');
    }

    public function resetOverride(string $key, ResetTenantTranslationOverride $resetOverride): void
    {
        $this->authorizeEditor();
        $this->statusMessage = null;
        $this->errorMessage = null;

        $user = auth()->user();
        abort_unless($user !== null, 403);

        try {
            $resetOverride($user, $this->locale, $key);
        } catch (TenantTranslationOverrideException $exception) {
            $this->errorMessage = __($exception->errorCode());

            return;
        } catch (AuthorizationException) {
            abort(403);
        }

        if ($this->editingKey === $key) {
            $this->cancelEditing();
        }

        $this->statusMessage = __('admin.translation_overrides.flash.reset');
    }

    public function clearSearch(): void
    {
        $this->search = '';
        $this->page = 1;
    }

    public function previousPage(): void
    {
        $this->page = max(1, $this->page - 1);
    }

    public function nextPage(): void
    {
        $this->page++;
    }

    public function updatedSearch(): void
    {
        $this->page = 1;
    }

    public function updatedLocale(): void
    {
        $this->page = 1;
        $this->cancelEditing();
        $this->normalizeState();
    }

    private function normalizeState(): void
    {
        if (! TenantTranslationOverrideRules::isSupportedLocale($this->locale)) {
            $this->locale = 'hy';
        }

        $this->page = max(1, $this->page);
    }

    private function authorizeEditor(): void
    {
        abort_unless(auth()->user()?->can(TenantTranslationOverridePermissions::MANAGE) ?? false, 403);
    }
}
