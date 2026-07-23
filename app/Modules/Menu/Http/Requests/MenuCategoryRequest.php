<?php

declare(strict_types=1);

namespace App\Modules\Menu\Http\Requests;

use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Support\I18n\LocalizedText;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

final class MenuCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string|Exists>>
     */
    public function rules(): array
    {
        $tenantId = app(TenantResolver::class)->id();
        $parentRules = [
            'nullable',
            'integer',
            'min:0',
        ];

        if ($this->integer('parent_id') > 0) {
            $parentRules[] = Rule::exists('menu_categories', 'id')
                ->where('tenant_id', $tenantId ?? 0)
                ->whereNull('deleted_at');
        }

        return [
            'parent_id' => $parentRules,
            'name_hy' => ['required', 'string', 'max:255'],
            'name_ru' => ['required', 'string', 'max:255'],
            'name_en' => ['required', 'string', 'max:255'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'parent_id' => __('menu.fields.parent_category'),
            'name_hy' => __('menu.fields.name_hy'),
            'name_ru' => __('menu.fields.name_ru'),
            'name_en' => __('menu.fields.name_en'),
            'sort_order' => __('menu.fields.sort_order'),
            'active' => __('menu.fields.active'),
        ];
    }

    public function localizedName(): LocalizedText
    {
        return LocalizedText::fromArray([
            'hy' => (string) $this->string('name_hy'),
            'ru' => (string) $this->string('name_ru'),
            'en' => (string) $this->string('name_en'),
        ]);
    }

    public function parentId(): ?int
    {
        $parentId = $this->integer('parent_id');

        return $parentId === 0 ? null : $parentId;
    }

    public function parentIdOr(?int $missingValue): ?int
    {
        if (! $this->has('parent_id')) {
            return $missingValue;
        }

        return $this->parentId();
    }

    public function sortOrder(): int
    {
        return (int) $this->integer('sort_order');
    }

    public function active(): bool
    {
        return $this->boolean('active');
    }
}
