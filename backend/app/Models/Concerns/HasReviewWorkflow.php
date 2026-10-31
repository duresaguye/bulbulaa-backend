<?php

namespace App\Models\Concerns;

use App\Enums\ReportStatus;
use App\Models\Department;
use App\Models\ReportReview;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Validation\ValidationException;

/**
 * Shared draft → submitted → returned/approved lifecycle for office reports.
 *
 * @property ReportStatus $status
 */
trait HasReviewWorkflow
{
    protected static function bootHasReviewWorkflow(): void
    {
        static::saving(function (Model $report): void {
            /** @var self $report */
            $originalStatus = ReportStatus::tryFrom((string) $report->getRawOriginal('status'));

            if ($report->exists && $originalStatus === ReportStatus::Approved) {
                throw ValidationException::withMessages([
                    'status' => 'An approved report cannot be changed.',
                ]);
            }
        });

        static::deleting(function (Model $report): void {
            /** @var self $report */
            if (! $report->status->isEditable()) {
                throw ValidationException::withMessages([
                    'status' => 'Only draft or returned reports can be deleted.',
                ]);
            }
        });
    }

    public function initializeHasReviewWorkflow(): void
    {
        $this->mergeCasts([
            'status' => ReportStatus::class,
            'submitted_at' => 'datetime',
            'evidence_paths' => 'array',
        ]);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviews(): MorphMany
    {
        return $this->morphMany(ReportReview::class, 'reviewable')->latest('id');
    }

    public function latestReturn(): MorphOne
    {
        return $this->morphOne(ReportReview::class, 'reviewable')->ofMany(
            ['id' => 'max'],
            fn (Builder $query) => $query->where('action', 'returned'),
        );
    }

    public function scopeReported(Builder $query): Builder
    {
        return $query->whereIn('status', ReportStatus::reportedValues());
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', ReportStatus::Approved->value);
    }

    public function isEditable(): bool
    {
        return $this->status->isEditable();
    }

    /**
     * Human-readable reporting period, for example "Tikimt 2019, week 2".
     */
    abstract public function periodLabel(): string;
}
