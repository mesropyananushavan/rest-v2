<?php

declare(strict_types=1);

namespace App\Support\I18n;

use InvalidArgumentException;

final readonly class LocalizedText
{
    /**
     * @var array{hy: string, ru: string, en: string}
     */
    private array $translations;

    /**
     * @param  array<string, mixed>  $translations
     */
    private function __construct(array $translations)
    {
        $this->translations = [
            'hy' => $this->requiredText($translations, 'hy'),
            'ru' => $this->requiredText($translations, 'ru'),
            'en' => $this->requiredText($translations, 'en'),
        ];
    }

    /**
     * @param  array<string, mixed>  $translations
     */
    public static function fromArray(array $translations): self
    {
        return new self($translations);
    }

    /**
     * @return array{hy: string, ru: string, en: string}
     */
    public function toArray(): array
    {
        return $this->translations;
    }

    public function forLocale(string $locale, string $fallbackLocale = 'en'): string
    {
        if (isset($this->translations[$locale])) {
            return $this->translations[$locale];
        }

        if (isset($this->translations[$fallbackLocale])) {
            return $this->translations[$fallbackLocale];
        }

        return $this->translations['en'];
    }

    /**
     * @param  array<string, mixed>  $translations
     */
    private function requiredText(array $translations, string $locale): string
    {
        $value = $translations[$locale] ?? null;

        if (! is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException("Missing localized text for {$locale}.");
        }

        return trim($value);
    }
}
