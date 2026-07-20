<?php

declare(strict_types=1);

namespace App\Modules\Menu\Http\Requests;

use App\Support\I18n\LocalizedText;
use App\Support\Money\Money;
use Illuminate\Foundation\Http\FormRequest;

final class MenuItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'min:1'],
            'name_hy' => ['required', 'string', 'max:255'],
            'name_ru' => ['required', 'string', 'max:255'],
            'name_en' => ['required', 'string', 'max:255'],
            'description_hy' => ['nullable', 'required_with:description_ru,description_en', 'string', 'max:1000'],
            'description_ru' => ['nullable', 'required_with:description_hy,description_en', 'string', 'max:1000'],
            'description_en' => ['nullable', 'required_with:description_hy,description_ru', 'string', 'max:1000'],
            'price_minor' => ['required', 'integer', 'min:0'],
            'currency' => ['required', 'string', 'regex:/^[A-Z]{3}$/'],
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
            'category_id' => __('menu.fields.category'),
            'name_hy' => __('menu.fields.name_hy'),
            'name_ru' => __('menu.fields.name_ru'),
            'name_en' => __('menu.fields.name_en'),
            'description_hy' => __('menu.fields.description_hy'),
            'description_ru' => __('menu.fields.description_ru'),
            'description_en' => __('menu.fields.description_en'),
            'price_minor' => __('menu.fields.price_minor'),
            'currency' => __('menu.fields.currency'),
            'sort_order' => __('menu.fields.sort_order'),
            'active' => __('menu.fields.active'),
        ];
    }

    public function categoryId(): int
    {
        return (int) $this->integer('category_id');
    }

    public function localizedName(): LocalizedText
    {
        return LocalizedText::fromArray([
            'hy' => (string) $this->string('name_hy'),
            'ru' => (string) $this->string('name_ru'),
            'en' => (string) $this->string('name_en'),
        ]);
    }

    public function localizedDescription(): ?LocalizedText
    {
        $description = [
            'hy' => (string) $this->string('description_hy'),
            'ru' => (string) $this->string('description_ru'),
            'en' => (string) $this->string('description_en'),
        ];

        if (trim($description['hy'].$description['ru'].$description['en']) === '') {
            return null;
        }

        return LocalizedText::fromArray($description);
    }

    public function price(): Money
    {
        return new Money((int) $this->integer('price_minor'), (string) $this->string('currency'));
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
