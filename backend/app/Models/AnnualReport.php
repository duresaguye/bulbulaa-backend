<?php

namespace App\Models;

use App\Models\Concerns\HasReviewWorkflow;
use App\Models\Concerns\RecordsAudit;
use Database\Factories\AnnualReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property list<array{target_id: int, title: string, unit: string, aggregation: string, original_target: float, current_target: float, actual: float|null, achievement: float|null}>|null $results_snapshot
 */
#[Fillable(['department_id', 'prepared_by', 'submitted_by', 'ethiopian_fiscal_year', 'key_accomplishments', 'missed_targets', 'corrective_actions', 'lessons_learned', 'evidence_paths', 'results_snapshot', 'submitted_at'])]
class AnnualReport extends Model
{
    /** @use HasFactory<AnnualReportFactory> */
    use HasFactory, HasReviewWorkflow, RecordsAudit;

    protected $attributes = [
        'status' => 'draft',
    ];

    public function periodLabel(): string
    {
        return __('Year :year', ['year' => $this->ethiopian_fiscal_year]);
    }

    protected function casts(): array
    {
        return [
            'ethiopian_fiscal_year' => 'integer',
            'results_snapshot' => 'array',
        ];
    }
}
