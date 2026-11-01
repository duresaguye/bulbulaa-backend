<?php

namespace App\Support;

/**
 * The text the public website shows before staff change anything, taken from the website's own data files
 * (resources/website-defaults.json). The admin starts its forms from it so staff edit rather than retype.
 */
class WebsiteDefaults
{
    /**
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        static $defaults = null;

        return $defaults ??= json_decode((string) file_get_contents(resource_path('website-defaults.json')), true) ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public static function section(string $key): array
    {
        return self::all()[$key] ?? [];
    }
}
