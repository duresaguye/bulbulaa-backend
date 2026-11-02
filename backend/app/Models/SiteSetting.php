<?php

namespace App\Models;

use App\Models\Concerns\RecordsAudit;
use App\Models\Concerns\TracksEditor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One section of website settings (mayor, contact, home page, city facts, investment park, about gallery),
 * stored as a whole. A section that was never saved is absent, and the website keeps its built-in text for it.
 */
#[Fillable(['key', 'value'])]
class SiteSetting extends Model
{
    use RecordsAudit;
    use TracksEditor;

    public const SECTIONS = ['mayor', 'contact', 'home', 'city', 'investment', 'about'];

    /**
     * @return array<string, mixed>|null
     */
    public static function section(string $key): ?array
    {
        return self::query()->where('key', $key)->value('value');
    }

    /**
     * @param  array<string, mixed>  $value
     */
    public static function store(string $key, array $value): void
    {
        $setting = self::query()->firstOrNew(['key' => $key]);
        $setting->value = $value;
        $setting->save();
    }

    protected function casts(): array
    {
        return ['value' => 'array'];
    }
}
