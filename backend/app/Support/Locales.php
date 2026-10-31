<?php

namespace App\Support;

/**
 * The languages staff can use the admin in. Afaan Oromoo is the default, as on the public website.
 */
class Locales
{
    /**
     * Each language's name in its own language, which is how people look for it.
     *
     * @var array<string, string>
     */
    public const NAMES = [
        'om' => 'Afaan Oromoo',
        'am' => 'አማርኛ',
        'en' => 'English',
    ];

    public static function isSupported(?string $locale): bool
    {
        return $locale !== null && array_key_exists($locale, self::NAMES);
    }

    public static function default(): string
    {
        $configured = config('app.locale');

        return self::isSupported($configured) ? $configured : 'om';
    }
}
