<?php

namespace App\Models;

use App\Enums\Aggregation;
use App\Models\Concerns\RecordsAudit;
use Database\Factories\AnnualTargetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

#[Fillable(['parent_id', 'department_id', 'assigned_by', 'fiscal_year', 'title', 'description', 'measurement_unit', 'aggregation', 'target_value', 'original_target_value', 'is_active'])]
class AnnualTarget extends Model
{
    /** @use HasFactory<AnnualTargetFactory> */
    use HasFactory, RecordsAudit;

    protected $attributes = [
        'aggregation' => 'sum',
    ];

    /**
     * Set by TargetAssignment::revise() once the revision row has been written.
     */
    public bool $revisionRecorded = false;

    protected static function booted(): void
    {
        static::creating(function (self $target): void {
            $target->original_target_value ??= $target->target_value;
        });

        static::updating(function (self $target): void {
            if ($target->isDirty('original_target_value')) {
                throw ValidationException::withMessages([
                    'target_value' => __('The originally assigned target value cannot be changed.'),
                ]);
            }

            if ($target->isDirty('target_value') && ! $target->revisionRecorded) {
                throw ValidationException::withMessages([
                    'target_value' => __('An assigned target can only change through a recorded revision with a reason.'),
                ]);
            }

            if ($target->isDirty(['department_id', 'fiscal_year', 'aggregation']) && $target->hasReportedProgress()) {
                throw ValidationException::withMessages([
                    'department_id' => __('The office, year, and indicator type cannot change after progress has been reported.'),
                ]);
            }
        });
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function monthlyPlans(): HasMany
    {
        return $this->hasMany(MonthlyPlan::class)->orderBy('ethiopian_month');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(AnnualTargetRevision::class)->latest('id');
    }

    public function hasReportedProgress(): bool
    {
        return WeeklyProgressEntry::query()
            ->whereHas('monthlyPlan', fn ($query) => $query->where('annual_target_id', $this->id))
            ->exists()
            || MonthlyReportLine::query()
                ->whereHas('monthlyPlan', fn ($query) => $query->where('annual_target_id', $this->id))
                ->exists();
    }

    public function allocatedTotal(): float
    {
        return (float) $this->monthlyPlans->sum('planned_value');
    }

    /**
     * For additive indicators, the difference between the monthly allocation and the annual target.
     * Null when the indicator is not additive or nothing has been allocated yet.
     */
    public function allocationGap(): ?float
    {
        if (! $this->aggregation->isAdditive() || $this->monthlyPlans->isEmpty()) {
            return null;
        }

        return round((float) $this->target_value - $this->allocatedTotal(), 2);
    }

    public function distributedTotal(): float
    {
        return (float) $this->children->sum('target_value');
    }

    public function formatValue(float|string|null $value): string
    {
        if ($value === null) {
            return '—';
        }

        $value = (float) $value;
        $formatted = number_format($value, floor($value) == $value ? 0 : 2);

        return str_contains(strtolower($this->measurement_unit), 'percent') || $this->measurement_unit === '%'
            ? $formatted.'%'
            : $formatted;
    }

    /**
     * A value with its unit for sentences, for example "90 requests" or "85%".
     */
    public function formatWithUnit(float|string|null $value): string
    {
        $formatted = $this->formatValue($value);

        return str_ends_with($formatted, '%') || $value === null ? $formatted : $formatted.' '.$this->measurement_unit;
    }

    protected function casts(): array
    {
        return [
            'fiscal_year' => 'integer',
            'aggregation' => Aggregation::class,
            'target_value' => 'decimal:2',
            'original_target_value' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
