<?php

namespace App\Models\Concerns;

/**
 * Text kept in all three website languages as {"om": …, "am": …, "en": …}. Afaan Oromoo is required and the
 * other languages fall back to it, so a visitor never sees an empty field.
 */
trait HasTranslations
{
    /**
     * One field in one language, falling back to Afaan Oromoo, then to any language that has text.
     */
    public function translated(string $field, string $locale): ?string
    {
        $values = $this->{$field} ?? [];

        return collect([$locale, 'om', 'am', 'en'])
            ->map(fn (string $code): ?string => filled($values[$code] ?? null) ? $values[$code] : null)
            ->first(fn (?string $value): bool => $value !== null);
    }

    /**
     * @return array{om: string|null, am: string|null, en: string|null}
     */
    public function allTranslations(string $field): array
    {
        return collect(['om', 'am', 'en'])->mapWithKeys(fn (string $code): array => [$code => $this->translated($field, $code)])->all();
    }
}
