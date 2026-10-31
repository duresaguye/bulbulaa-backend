<?php

namespace App\Models;

use App\Enums\ReviewAction;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One entry in a report's submission and review history. Entries are never edited.
 */
#[Fillable(['reviewable_type', 'reviewable_id', 'user_id', 'action', 'comment'])]
class ReportReview extends Model
{
    public function reviewable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'action' => ReviewAction::class,
        ];
    }
}
