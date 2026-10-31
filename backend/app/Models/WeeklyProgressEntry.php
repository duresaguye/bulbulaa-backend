<?php

namespace App\Models;

use App\Models\Concerns\RecordsAudit;
use Database\Factories\WeeklyProgressEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

#[Fillable(['weekly_progress_report_id', 'monthly_plan_id', 'actual_value', 'progress_summary'])]
class WeeklyProgressEntry extends Model
{
    /** @use HasFactory<WeeklyProgressEntryFactory> */
    use HasFactory, RecordsAudit;

    protected static function booted(): void
    {
        static::saving(function (self $entry): void {
            $report = $entry->report;
            $monthlyPlan = $entry->monthlyPlan()->with('annualTarget')->first();

            if ($report === null || $monthlyPlan === null
                || $monthlyPlan->annualTarget->department_id !== $report->department_id
                || $monthlyPlan->annualTarget->fiscal_year !== $report->ethiopian_fiscal_year
                || $monthlyPlan->ethiopian_month !== $report->ethiopian_month) {
                throw ValidationException::withMessages([
                    'monthly_plan_id' => __('The monthly plan must belong to the report department and Ethiopian reporting month.'),
                ]);
            }

            if (! $report->isEditable()) {
                throw ValidationException::withMessages([
                    'actual_value' => __('Progress can only be changed while the report is a draft or returned for correction.'),
                ]);
            }
        });

        static::deleting(function (self $entry): void {
            if ($entry->report !== null && ! $entry->report->isEditable()) {
                throw ValidationException::withMessages([
                    'actual_value' => __('Progress can only be changed while the report is a draft or returned for correction.'),
                ]);
            }
        });
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(WeeklyProgressReport::class, 'weekly_progress_report_id');
    }

    public function monthlyPlan(): BelongsTo
    {
        return $this->belongsTo(MonthlyPlan::class);
    }

    protected function casts(): array
    {
        return [
            'actual_value' => 'decimal:2',
        ];
    }
}
