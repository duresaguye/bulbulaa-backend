<?php

namespace App\Models;

use App\Models\Concerns\RecordsAudit;
use Database\Factories\MonthlyPlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['annual_target_id', 'ethiopian_month', 'planned_value', 'planned_activities'])]
class MonthlyPlan extends Model
{
    /** @use HasFactory<MonthlyPlanFactory> */
    use HasFactory, RecordsAudit;

    public const ETHIOPIAN_MONTHS = [
        1 => 'Meskerem',
        2 => 'Tikimt',
        3 => 'Hidar',
        4 => 'Tahsas',
        5 => 'Tir',
        6 => 'Yekatit',
        7 => 'Megabit',
        8 => 'Miyazya',
        9 => 'Ginbot',
        10 => 'Sene',
        11 => 'Hamle',
        12 => 'Nehase',
        13 => 'Pagume',
    ];

    public function annualTarget(): BelongsTo
    {
        return $this->belongsTo(AnnualTarget::class);
    }

    public function ethiopianMonthName(): string
    {
        return __(self::ETHIOPIAN_MONTHS[$this->ethiopian_month]);
    }

    public function weeklyProgressEntries(): HasMany
    {
        return $this->hasMany(WeeklyProgressEntry::class);
    }

    public function monthlyReportLines(): HasMany
    {
        return $this->hasMany(MonthlyReportLine::class);
    }

    protected function casts(): array
    {
        return [
            'ethiopian_month' => 'integer',
            'planned_value' => 'decimal:2',
        ];
    }
}
