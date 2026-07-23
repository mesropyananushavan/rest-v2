<?php

declare(strict_types=1);

namespace App\Modules\Menu\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class MenuItemIndexRequest extends FormRequest
{
    private const DEFAULT_PER_PAGE = 25;

    private const MAX_PER_PAGE = 50;

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
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1'],
            'category_id' => ['sometimes', 'integer', 'min:1'],
            'search' => ['sometimes', 'string', 'max:120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'page.integer' => __('api.validation.integer'),
            'page.min' => __('api.validation.min'),
            'per_page.integer' => __('api.validation.integer'),
            'per_page.min' => __('api.validation.min'),
            'category_id.integer' => __('api.validation.integer'),
            'category_id.min' => __('api.validation.min'),
            'search.string' => __('api.validation.string'),
            'search.max' => __('api.validation.max'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'page' => __('api.fields.page'),
            'per_page' => __('api.fields.per_page'),
            'category_id' => __('api.fields.category_id'),
            'search' => __('api.fields.search'),
        ];
    }

    public function page(): int
    {
        return max(1, (int) $this->integer('page', 1));
    }

    public function perPage(): int
    {
        return min(self::MAX_PER_PAGE, max(1, (int) $this->integer('per_page', self::DEFAULT_PER_PAGE)));
    }

    public function categoryId(): ?int
    {
        if (! $this->has('category_id')) {
            return null;
        }

        return (int) $this->integer('category_id');
    }

    public function searchQuery(): ?string
    {
        $search = $this->query('search');

        if (! is_string($search)) {
            return null;
        }

        $search = trim($search);

        return $search === '' ? null : $search;
    }
}
