<?php

namespace App\Models;

use App\Enums\DocumentCategory;
use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\RecordsAudit;
use App\Models\Concerns\TracksEditor;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * A public document to download from the website: a form, proclamation, report or guideline.
 * Files are public on purpose; never upload anything personal or internal here.
 */
#[Fillable(['title', 'description', 'category', 'file', 'file_name', 'file_size', 'published_at'])]
class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory;

    use HasTranslations;
    use RecordsAudit;
    use TracksEditor;

    public const DISK = 'public';

    protected static function booted(): void
    {
        static::saving(function (Document $document): void {
            if ($document->isDirty('file') && $document->file && Storage::disk(self::DISK)->exists($document->file)) {
                $document->file_size = Storage::disk(self::DISK)->size($document->file);
            }
        });

        static::updated(function (Document $document): void {
            $previous = $document->getOriginal('file');

            if ($document->wasChanged('file') && $previous) {
                Storage::disk(self::DISK)->delete($previous);
            }
        });

        static::deleted(function (Document $document): void {
            Storage::disk(self::DISK)->delete($document->file);
        });
    }

    public function scopePublished(Builder $query): void
    {
        $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null && $this->published_at->isPast();
    }

    /** The file's type in capitals, such as PDF or DOCX, for the download button. */
    public function fileType(): string
    {
        return strtoupper(pathinfo($this->file_name ?: $this->file, PATHINFO_EXTENSION));
    }

    protected function casts(): array
    {
        return [
            'title' => 'array',
            'description' => 'array',
            'category' => DocumentCategory::class,
            'published_at' => 'datetime',
        ];
    }
}
