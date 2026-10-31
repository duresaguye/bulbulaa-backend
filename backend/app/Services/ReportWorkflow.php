<?php

namespace App\Services;

use App\Enums\ReportStatus;
use App\Enums\ReviewAction;
use App\Models\AnnualReport;
use App\Models\Department;
use App\Models\MonthlyReport;
use App\Models\User;
use App\Models\WeeklyProgressReport;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The only place report statuses change. Every transition is authorised here and recorded in the review history.
 */
class ReportWorkflow
{
    public function __construct(private TargetProgress $progress) {}

    public function canSubmit(User $user, WeeklyProgressReport|MonthlyReport|AnnualReport $report): bool
    {
        return $report->isEditable()
            && $user->hasPermission('reports.submit')
            && $user->belongsToDepartment((int) $report->department_id);
    }

    public function submit(User $user, WeeklyProgressReport|MonthlyReport|AnnualReport $report): void
    {
        if (! $this->canSubmit($user, $report)) {
            throw new AuthorizationException(__('You are not allowed to submit this report.'));
        }

        if ($report instanceof WeeklyProgressReport && ! $report->entries()->exists()) {
            throw ValidationException::withMessages([
                'entries' => __('Record progress against at least one monthly target before submitting.'),
            ]);
        }

        DB::transaction(function () use ($user, $report): void {
            if ($report instanceof AnnualReport) {
                $report->results_snapshot = $this->annualResults($report->department, $report->ethiopian_fiscal_year);
            }

            $report->status = ReportStatus::Submitted;
            $report->submitted_by = $user->id;
            $report->submitted_at = now();
            $report->save();

            $report->reviews()->create([
                'user_id' => $user->id,
                'action' => ReviewAction::Submitted,
            ]);
        });
    }

    public function approve(User $user, WeeklyProgressReport|MonthlyReport|AnnualReport $report, ?string $comment = null): void
    {
        $this->authorizeReview($user, $report);

        DB::transaction(function () use ($user, $report, $comment): void {
            $report->status = ReportStatus::Approved;
            $report->save();

            $report->reviews()->create([
                'user_id' => $user->id,
                'action' => ReviewAction::Approved,
                'comment' => filled($comment) ? $comment : null,
            ]);
        });
    }

    public function return(User $user, WeeklyProgressReport|MonthlyReport|AnnualReport $report, string $comment): void
    {
        $this->authorizeReview($user, $report);

        if (blank($comment)) {
            throw ValidationException::withMessages([
                'comment' => __('Explain what needs to be corrected so the office can fix it.'),
            ]);
        }

        DB::transaction(function () use ($user, $report, $comment): void {
            $report->status = ReportStatus::Returned;
            $report->save();

            $report->reviews()->create([
                'user_id' => $user->id,
                'action' => ReviewAction::Returned,
                'comment' => $comment,
            ]);
        });
    }

    /**
     * Who reviews reports from this office, in plain words for the person submitting.
     */
    public static function reviewerDescription(Department $department): string
    {
        return $department->parent !== null
            ? __(':office will review it.', ['office' => $department->parent->name])
            : __('The city administration will review it.');
    }

    /**
     * Annual results per target, calculated only from approved monthly data.
     *
     * @return list<array{target_id: int, title: string, unit: string, aggregation: string, original_target: float, current_target: float, actual: float|null, achievement: float|null}>
     */
    public function annualResults(Department $department, int $fiscalYear): array
    {
        return $department->annualTargets()
            ->where('fiscal_year', $fiscalYear)
            ->where('is_active', true)
            ->with(['monthlyPlans', 'children.monthlyPlans'])
            ->orderBy('title')
            ->get()
            ->map(function ($target): array {
                $actual = $this->progress->annualActual($target, approvedOnly: true);

                return [
                    'target_id' => $target->id,
                    'title' => $target->title,
                    'unit' => $target->measurement_unit,
                    'aggregation' => $target->aggregation->value,
                    'original_target' => (float) $target->original_target_value,
                    'current_target' => (float) $target->target_value,
                    'actual' => $actual,
                    'achievement' => TargetProgress::achievement($actual, $target->target_value),
                ];
            })
            ->all();
    }

    private function authorizeReview(User $user, WeeklyProgressReport|MonthlyReport|AnnualReport $report): void
    {
        if ($report->status !== ReportStatus::Submitted) {
            throw ValidationException::withMessages([
                'status' => __('Only reports awaiting review can be approved or returned.'),
            ]);
        }

        if (! $user->canReview($report)) {
            throw new AuthorizationException(__('You are not allowed to review this report.'));
        }
    }
}
