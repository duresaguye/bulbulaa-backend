<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\RecordsAudit;
use App\Models\Concerns\TracksEditor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * An area the city invites investment in, with the things that can be invested in, per language.
 */
#[Fillable(['kind', 'title', 'description', 'items', 'image', 'sort_order', 'is_published'])]
class InvestmentSector extends Model
{
    use HasTranslations;
    use RecordsAudit;
    use TracksEditor;

    /**
     * Kinds the website knows, so it can pick the right photo and show the tourism sector on the tourism page.
     *
     * @var array<string, string>
     */
    public const KINDS = [
        'agriculture' => 'Agriculture',
        'livestock' => 'Livestock',
        'industry' => 'Industry',
        'tourism' => 'Tourism',
        'other' => 'Other',
    ];

    protected static function booted(): void
    {
        static::deleted(function (InvestmentSector $sector): void {
            if ($sector->image) {
                Storage::disk('public')->delete($sector->image);
            }
        });
    }

    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true);
    }

    /**
     * The list of things to invest in for one language, falling back to Afaan Oromoo.
     *
     * @return list<string>
     */
    public function itemsIn(string $locale): array
    {
        $items = $this->items ?? [];

        return array_values(array_filter($items[$locale] ?? []) ?: array_filter($items['om'] ?? []));
    }

    protected function casts(): array
    {
        return [
            'title' => 'array',
            'description' => 'array',
            'items' => 'array',
            'is_published' => 'boolean',
        ];
    }
}
