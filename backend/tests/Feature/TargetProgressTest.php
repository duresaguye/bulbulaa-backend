<?php

namespace Tests\Feature;

use App\Enums\ReportStatus;
use App\Models\AnnualTarget;
use App\Models\MonthlyReport;
use App\Models\MonthlyReportLine;
use App\Models\WeeklyProgressEntry;
use App\Models\WeeklyProgressReport;
use App\Services\TargetProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsOrganization;
use Tests\TestCase;

class TargetProgressTest extends TestCase
{
    use BuildsOrganization;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildOrganization();
    }

    public function test_additive_targets_sum_weekly_results_and_ignore_drafts(): void
    {
        $target = $this->targetWithPlans($this->office, 1200, [1 => 80, 2 => 90, 3 => 100]);

        $this->weekly($target, month: 1, week: 1, value: 40, status: ReportStatus::Submitted);
        $this->weekly($target, month: 1, week: 2, value: 35, status: ReportStatus::Approved);
        $this->weekly($target, month: 1, week: 3, value: 500, status: ReportStatus::Draft);
        $this->weekly($target, month: 2, week: 1, value: 200, status: ReportStatus::Returned);

        $progress = new TargetProgress;

        $this->assertSame(75.0, $progress->monthlyActuals($target)[1]);
        $this->assertNull($progress->monthlyActuals($target)[2]);
        $this->assertSame(75.0, $progress->annualActual($target));
        $this->assertSame(93.8, TargetProgress::achievement(75, 80));
    }

    public function test_a_monthly_report_replaces_weekly_figures_for_its_month(): void
    {
        $target = $this->targetWithPlans($this->office, 1200, [1 => 80]);
        $this->weekly($target, month: 1, week: 1, value: 40, status: ReportStatus::Submitted);
        $this->monthly($target, month: 1, value: 78, status: ReportStatus::Submitted);

        $this->assertSame(78.0, (new TargetProgress)->annualActual($target));
    }

    public function test_approved_only_calculations_ignore_unapproved_data(): void
    {
        $target = $this->targetWithPlans($this->office, 1200, [1 => 80, 2 => 90]);
        $this->weekly($target, month: 1, week: 1, value: 40, status: ReportStatus::Approved);
        $this->monthly($target, month: 2, value: 90, status: ReportStatus::Submitted);

        $this->assertNull((new TargetProgress)->annualActual($target, approvedOnly: true));

        $this->monthly($target, month: 1, value: 79, status: ReportStatus::Approved);

        $this->assertSame(79.0, (new TargetProgress)->annualActual($target, approvedOnly: true));
    }

    public function test_percentage_indicators_are_averaged_not_summed(): void
    {
        $target = $this->targetWithPlans($this->office, 90, [1 => 85, 2 => 90], 'average');
        $this->monthly($target, month: 1, value: 80, status: ReportStatus::Submitted);
        $this->monthly($target, month: 2, value: 90, status: ReportStatus::Submitted);

        $this->assertSame(85.0, (new TargetProgress)->annualActual($target));
        $this->assertSame(90.0, (new TargetProgress)->expectedToDate($target, 2));
    }

    public function test_current_level_indicators_use_the_latest_value(): void
    {
        $target = $this->targetWithPlans($this->office, 60, [1 => 20, 2 => 40], 'latest');
        $this->monthly($target, month: 1, value: 25, status: ReportStatus::Submitted);
        $this->monthly($target, month: 2, value: 38, status: ReportStatus::Submitted);

        $this->assertSame(38.0, (new TargetProgress)->annualActual($target));
    }

    public function test_pace_compares_results_with_the_allocation_to_date(): void
    {
        $target = $this->targetWithPlans($this->office, 1200, [1 => 100, 2 => 100]);
        $this->monthly($target, month: 1, value: 60, status: ReportStatus::Submitted);

        $summary = (new TargetProgress)->summary($target, 2);

        $this->assertSame(200.0, $summary['expected']);
        $this->assertSame(30.0, $summary['pace']);
        $this->assertSame('behind', $summary['status']);
        $this->assertSame(1140.0, $summary['remaining']);
        $this->assertSame('not_started', (new TargetProgress)->summary($target, 0)['status']);
    }

    public function test_distributed_additive_targets_roll_up_office_results(): void
    {
        $parent = $this->targetWithPlans($this->department, 1000, []);
        $child = $this->targetWithPlans($this->office, 400, [1 => 50]);
        $child->update(['parent_id' => $parent->id]);
        $this->monthly($child, month: 1, value: 45, status: ReportStatus::Submitted);

        $this->assertSame(45.0, (new TargetProgress)->annualActual($parent->fresh(['monthlyPlans', 'children.monthlyPlans'])));
    }

    public function test_allocation_gap_is_only_reported_for_additive_indicators(): void
    {
        $this->assertSame(1020.0, $this->targetWithPlans($this->office, 1200, [1 => 80, 2 => 100])->allocationGap());
        $this->assertNull($this->targetWithPlans($this->office, 90, [1 => 80, 2 => 100], 'average')->allocationGap());
    }

    private function weekly(AnnualTarget $target, int $month, int $week, float $value, ReportStatus $status): void
    {
        $report = WeeklyProgressReport::factory()->create([
            'department_id' => $target->department_id,
            'ethiopian_fiscal_year' => $target->fiscal_year,
            'ethiopian_month' => $month,
            'week_number' => $week,
        ]);
        WeeklyProgressEntry::factory()->create([
            'weekly_progress_report_id' => $report->id,
            'monthly_plan_id' => $target->monthlyPlans->firstWhere('ethiopian_month', $month)->id,
            'actual_value' => $value,
        ]);
        $report->forceFill(['status' => $status])->save();
    }

    private function monthly(AnnualTarget $target, int $month, float $value, ReportStatus $status): void
    {
        $report = MonthlyReport::factory()->create([
            'department_id' => $target->department_id,
            'ethiopian_fiscal_year' => $target->fiscal_year,
            'ethiopian_month' => $month,
        ]);
        MonthlyReportLine::create([
            'monthly_report_id' => $report->id,
            'monthly_plan_id' => $target->monthlyPlans->firstWhere('ethiopian_month', $month)->id,
            'actual_value' => $value,
        ]);
        $report->forceFill(['status' => $status])->save();
    }
}
