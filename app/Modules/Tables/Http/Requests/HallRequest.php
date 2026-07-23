<?php

declare(strict_types=1);

namespace App\Modules\Tables\Http\Requests;

use App\Support\I18n\LocalizedText;
use Illuminate\Foundation\Http\FormRequest;

final class HallRequest extends FormRequest
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
            'color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
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
            'name_hy' => __('tables.halls.fields.name_hy'),
            'name_ru' => __('tables.halls.fields.name_ru'),
            'name_en' => __('tables.halls.fields.name_en'),
            'color' => __('tables.halls.fields.color'),
            'sort_order' => __('tables.halls.fields.sort_order'),
            'active' => __('tables.halls.fields.active'),
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

    public function color(): string
    {
        return strtoupper((string) $this->string('color'));
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
