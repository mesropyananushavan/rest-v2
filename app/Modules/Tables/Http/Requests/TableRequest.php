<?php

declare(strict_types=1);

namespace App\Modules\Tables\Http\Requests;

use App\Support\I18n\LocalizedText;
use Illuminate\Foundation\Http\FormRequest;

final class TableRequest extends FormRequest
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
            'name_hy' => ['required', 'string', 'max:255'],
            'name_ru' => ['required', 'string', 'max:255'],
            'name_en' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:standard,vip'],
            'shape' => ['required', 'string', 'in:circle,square,rectangle'],
            'hdm_department' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_delivery' => ['nullable', 'boolean'],
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
            'name_hy' => __('tables.tables.fields.name_hy'),
            'name_ru' => __('tables.tables.fields.name_ru'),
            'name_en' => __('tables.tables.fields.name_en'),
            'type' => __('tables.tables.fields.type'),
            'shape' => __('tables.tables.fields.shape'),
            'hdm_department' => __('tables.tables.fields.hdm_department'),
            'is_delivery' => __('tables.tables.fields.is_delivery'),
            'sort_order' => __('tables.tables.fields.sort_order'),
            'active' => __('tables.tables.fields.active'),
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

    public function type(): string
    {
        return (string) $this->string('type');
    }

    public function shape(): string
    {
        return (string) $this->string('shape');
    }

    public function hdmDepartment(): ?int
    {
        if ($this->input('hdm_department') === null || $this->input('hdm_department') === '') {
            return null;
        }

        return (int) $this->integer('hdm_department');
    }

    public function isDelivery(): bool
    {
        return $this->boolean('is_delivery');
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
