<?php

namespace App\Models;

use App\Enums\PostType;
use App\Models\Concerns\RecordsAudit;
use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A post on the public website. Afaan Oromoo text is required; Amharic and English fall back to it when empty.
 */
#[Fillable(['type', 'slug', 'title', 'excerpt', 'body', 'cover_image', 'attachment', 'deadline_on', 'is_featured', 'published_at'])]
class Post extends Model
{
    /** @use HasFactory<PostFactory> */
    use HasFactory;

    use RecordsAudit;

    public const LOCALES = ['om', 'am', 'en'];

    public const DEFAULT_LOCALE = 'om';

    protected static function booted(): void
    {
        static::saving(function (Post $post): void {
            if (blank($post->slug)) {
                $post->slug = self::uniqueSlug($post->title['en'] ?? $post->title[self::DEFAULT_LOCALE] ?? 'post');
            }

            if (auth()->check()) {
                $post->updated_by = auth()->id();
                $post->created_by ??= auth()->id();
            }
        });
    }

    /**
     * Posts the public may see: published, and not scheduled for later.
     */
    public function scopePublished(Builder $query): void
    {
        $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null && $this->published_at->isPast();
    }

    public function isScheduled(): bool
    {
        return $this->published_at !== null && $this->published_at->isFuture();
    }

    /**
     * One field in one language, falling back to Afaan Oromoo, then to any language that has text.
     */
    public function translated(string $field, string $locale): ?string
    {
        $values = $this->{$field} ?? [];

        return collect([$locale, self::DEFAULT_LOCALE, ...self::LOCALES])
            ->map(fn (string $code): ?string => filled($values[$code] ?? null) ? $values[$code] : null)
            ->first(fn (?string $value): bool => $value !== null);
    }

    /**
     * @return array{om: string|null, am: string|null, en: string|null}
     */
    public function allTranslations(string $field): array
    {
        return collect(self::LOCALES)->mapWithKeys(fn (string $code): array => [$code => $this->translated($field, $code)])->all();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * A web address made from the title, unique among posts. Ethiopic titles fall back to "post-<date>".
     */
    public static function uniqueSlug(string $title): string
    {
        $base = Str::slug(Str::limit($title, 80, '')) ?: 'post-'.now()->format('Y-m-d');
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
            'type' => PostType::class,
            'title' => 'array',
            'excerpt' => 'array',
            'body' => 'array',
            'deadline_on' => 'date',
            'is_featured' => 'boolean',
            'published_at' => 'datetime',
        ];
    }
}
