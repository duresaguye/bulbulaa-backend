<?php

namespace App\Models;

use App\Enums\AlertLevel;
use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\RecordsAudit;
use App\Models\Concerns\TracksEditor;
use Database\Factories\AlertFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A short message shown at the top of every website page, for example a water interruption or a road closure.
 */
#[Fillable(['message', 'level', 'link_url', 'link_label', 'starts_at', 'ends_at', 'is_active'])]
class Alert extends Model
{
    /** @use HasFactory<AlertFactory> */
    use HasFactory;

    use HasTranslations;
    use RecordsAudit;
    use TracksEditor;

    /**
     * Alerts showing right now: switched on, already started, and not yet ended.
     */
    public function scopeCurrent(Builder $query): void
    {
        $query->where('is_active', true)
            ->where(fn (Builder $query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }

    public function isShowing(): bool
    {
        return $this->is_active
            && ($this->starts_at === null || $this->starts_at->isPast())
            && ($this->ends_at === null || $this->ends_at->isFuture());
    }

    protected function casts(): array
    {
        return [
            'message' => 'array',
            'level' => AlertLevel::class,
            'link_label' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }
}
