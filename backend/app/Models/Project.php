<?php

namespace App\Models;

use App\Enums\ProjectStatus;
use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\RecordsAudit;
use App\Models\Concerns\TracksEditor;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A city project shown on the website, with a photo gallery. Photos are public, on the public disk.
 */
#[Fillable(['slug', 'title', 'category', 'summary', 'body', 'status', 'images', 'sort_order', 'is_published'])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    use HasTranslations;
    use RecordsAudit;
    use TracksEditor;

    protected static function booted(): void
    {
        static::saving(function (Project $project): void {
            if (blank($project->slug)) {
                $project->slug = self::uniqueSlug($project->title['en'] ?? $project->title['om'] ?? 'project');
            }
        });

        static::updated(function (Project $project): void {
            if ($project->wasChanged('images')) {
                // Photos taken out of the gallery are deleted from storage too.
                $removed = array_diff($project->getOriginal('images') ?? [], $project->images ?? []);
                Storage::disk('public')->delete(array_values($removed));
            }
        });

        static::deleted(function (Project $project): void {
            Storage::disk('public')->delete($project->images ?? []);
        });
    }

    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true);
    }

    public static function uniqueSlug(string $title): string
    {
        $base = Str::slug(Str::limit($title, 80, '')) ?: 'project-'.now()->format('Y-m-d');
        $slug = $base;
        $suffix = 2;

        while (self::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    protected function casts(): array
    {
        return [
            'title' => 'array',
            'category' => 'array',
            'summary' => 'array',
            'body' => 'array',
            'status' => ProjectStatus::class,
            'images' => 'array',
            'is_published' => 'boolean',
        ];
    }
}
