<?php

namespace App\Models;

use App\Models\Concerns\RecordsAudit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

#[Fillable(['monthly_report_id', 'monthly_plan_id', 'actual_value', 'variance_note'])]
class MonthlyReportLine extends Model
{
    use RecordsAudit;

    protected static function booted(): void
    {
        static::saving(function (self $line): void {
            $report = $line->report;
            $monthlyPlan = $line->monthlyPlan()->with('annualTarget')->first();

            if ($report === null || $monthlyPlan === null
                || $monthlyPlan->annualTarget->department_id !== $report->department_id
                || $monthlyPlan->annualTarget->fiscal_year !== $report->ethiopian_fiscal_year
                || $monthlyPlan->ethiopian_month !== $report->ethiopian_month) {
                throw ValidationException::withMessages([
                    'monthly_plan_id' => 'The monthly target must belong to the report office and month.',
                ]);
            }

            if (! $report->isEditable()) {
                throw ValidationException::withMessages([
                    'actual_value' => 'Results can only be changed while the report is a draft or returned for correction.',
                ]);
            }
        });
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(MonthlyReport::class, 'monthly_report_id');
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
