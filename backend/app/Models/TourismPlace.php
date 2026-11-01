<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\RecordsAudit;
use App\Models\Concerns\TracksEditor;
use Database\Factories\TourismPlaceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * A place to visit around Bulbulaa, shown on the website's tourism page.
 */
#[Fillable(['name', 'kind', 'description', 'image', 'sort_order', 'is_published'])]
class TourismPlace extends Model
{
    /** @use HasFactory<TourismPlaceFactory> */
    use HasFactory;

    use HasTranslations;
    use RecordsAudit;
    use TracksEditor;

    protected static function booted(): void
    {
        static::deleted(function (TourismPlace $place): void {
            if ($place->image) {
                Storage::disk('public')->delete($place->image);
            }
        });
    }

    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true);
    }

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'kind' => 'array',
            'description' => 'array',
            'is_published' => 'boolean',
        ];
    }
}
