<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\RecordsAudit;
use App\Models\Concerns\TracksEditor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A titled block of text on the website's About page, such as history, people or economy.
 */
#[Fillable(['title', 'body', 'sort_order', 'is_published'])]
class AboutSection extends Model
{
    use HasTranslations;
    use RecordsAudit;
    use TracksEditor;

    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true);
    }

    protected function casts(): array
    {
        return [
            'title' => 'array',
            'body' => 'array',
            'is_published' => 'boolean',
        ];
    }
}
