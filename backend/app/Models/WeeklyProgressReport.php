<?php

namespace App\Models;

use App\Models\Concerns\HasReviewWorkflow;
use App\Models\Concerns\RecordsAudit;
use App\Services\EthiopianCalendar;
use Database\Factories\WeeklyProgressReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

#[Fillable(['department_id', 'prepared_by', 'submitted_by', 'ethiopian_fiscal_year', 'ethiopian_month', 'week_number', 'achievements', 'challenges', 'next_steps', 'evidence_paths', 'submitted_at'])]
class WeeklyProgressReport extends Model
{
    /** @use HasFactory<WeeklyProgressReportFactory> */
    use HasFactory, HasReviewWorkflow, RecordsAudit;

    protected $attributes = [
        'status' => 'draft',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $report): void {
            if ($report->exists
                && $report->isDirty(['department_id', 'ethiopian_fiscal_year', 'ethiopian_month'])
                && $report->entries()->exists()) {
                throw ValidationException::withMessages([
                    'department_id' => 'A report period cannot be changed after progress entries have been added.',
                ]);
            }
        });
    }

    public function entries(): HasMany
    {
        return $this->hasMany(WeeklyProgressEntry::class);
    }

    public function periodLabel(): string
    {
        return EthiopianCalendar::monthName($this->ethiopian_month).' '.$this->ethiopian_fiscal_year.', week '.$this->week_number;
    }

    protected function casts(): array
    {
        return [
            'ethiopian_fiscal_year' => 'integer',
            'ethiopian_month' => 'integer',
            'week_number' => 'integer',
        ];
    }
}
