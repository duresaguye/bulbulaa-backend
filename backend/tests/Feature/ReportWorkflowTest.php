<?php

namespace Tests\Feature;

use App\Enums\ReportStatus;
use App\Enums\ReviewAction;
use App\Models\AnnualReport;
use App\Models\Department;
use App\Models\MonthlyReport;
use App\Models\MonthlyReportLine;
use App\Models\Permission;
use App\Models\WeeklyProgressEntry;
use App\Models\WeeklyProgressReport;
use App\Services\ReportWorkflow;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsOrganization;
use Tests\TestCase;

class ReportWorkflowTest extends TestCase
{
    use BuildsOrganization;
    use RefreshDatabase;

    private ReportWorkflow $workflow;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildOrganization();
        $this->workflow = app(ReportWorkflow::class);
    }

    public function test_an_office_head_submits_and_the_supervisor_approves(): void
    {
        $report = $this->weeklyReportWithEntry();
        $officeHead = $this->officeHead();
        $supervisor = $this->supervisor();

        $this->workflow->submit($officeHead, $report);

        $this->assertSame(ReportStatus::Submitted, $report->status);
        $this->assertTrue($report->submittedBy->is($officeHead));
        $this->assertNotNull($report->submitted_at);

        $this->workflow->approve($supervisor, $report, 'Figures checked.');

        $this->assertSame(ReportStatus::Approved, $report->fresh()->status);
        $this->assertSame(
            [ReviewAction::Approved, ReviewAction::Submitted],
            $report->reviews()->get()->pluck('action')->all(),
        );
        $this->assertDatabaseHas('report_reviews', ['user_id' => $supervisor->id, 'action' => 'approved', 'comment' => 'Figures checked.']);
    }

    public function test_reporting_officers_cannot_submit(): void
    {
        $report = $this->weeklyReportWithEntry();

        $this->expectException(AuthorizationException::class);

        $this->workflow->submit($this->reportingOfficer(), $report);
    }

    public function test_an_empty_weekly_report_cannot_be_submitted(): void
    {
        $report = WeeklyProgressReport::factory()->create(['department_id' => $this->office->id]);

        $this->expectException(ValidationException::class);

        $this->workflow->submit($this->officeHead(), $report);
    }

    public function test_nobody_reviews_a_report_they_submitted(): void
    {
        $city = $this->cityPlanner();
        $city->roles()->first()->permissions()->attach(
            Permission::firstOrCreate(['name' => 'reports.submit'], ['label' => 'Submit'])
        );
        $city = $city->fresh();
        $report = WeeklyProgressReport::factory()->create(['department_id' => $this->city->id, 'prepared_by' => $city->id]);
        WeeklyProgressEntry::factory()->create(['weekly_progress_report_id' => $report->id]);
        $this->workflow->submit($city, $report);

        $this->assertFalse($city->canReview($report));
        $this->expectException(AuthorizationException::class);

        $this->workflow->approve($city, $report);
    }

    public function test_reviewers_cannot_review_offices_outside_their_branch(): void
    {
        $report = $this->weeklyReportWithEntry($this->otherOffice);
        $this->workflow->submit($this->officeHead($this->otherOffice), $report);

        $this->expectException(AuthorizationException::class);

        $this->workflow->approve($this->supervisor(), $report);
    }

    public function test_returning_requires_a_comment_and_allows_correction_and_resubmission(): void
    {
        $report = $this->weeklyReportWithEntry();
        $officeHead = $this->officeHead();
        $supervisor = $this->supervisor();
        $this->workflow->submit($officeHead, $report);

        try {
            $this->workflow->return($supervisor, $report, ' ');
            $this->fail('A return without a comment was accepted.');
        } catch (ValidationException) {
            $this->assertSame(ReportStatus::Submitted, $report->fresh()->status);
        }

        $this->workflow->return($supervisor, $report, 'Week 2 figures are missing.');
        $report->refresh();

        $this->assertSame(ReportStatus::Returned, $report->status);
        $this->assertSame('Week 2 figures are missing.', $report->latestReturn->comment);

        $report->entries()->first()->update(['actual_value' => 12]);
        $this->workflow->submit($officeHead, $report);

        $this->assertSame(ReportStatus::Submitted, $report->fresh()->status);
    }

    public function test_submitted_results_are_locked_and_approved_reports_cannot_change(): void
    {
        $report = $this->weeklyReportWithEntry();
        $this->workflow->submit($this->officeHead(), $report);

        try {
            $report->entries()->first()->update(['actual_value' => 999]);
            $this->fail('A submitted entry was changed.');
        } catch (ValidationException) {
        }

        $this->workflow->approve($this->supervisor(), $report);

        $this->expectException(ValidationException::class);

        $report->fresh()->update(['achievements' => 'Rewritten after approval']);
    }

    public function test_annual_reports_record_approved_results_at_submission(): void
    {
        $target = $this->targetWithPlans($this->office, 1200, [1 => 100]);
        $monthly = MonthlyReport::factory()->create(['department_id' => $this->office->id, 'ethiopian_fiscal_year' => 2018, 'ethiopian_month' => 1]);
        MonthlyReportLine::create(['monthly_report_id' => $monthly->id, 'monthly_plan_id' => $target->monthlyPlans->first()->id, 'actual_value' => 90]);
        $officeHead = $this->officeHead();
        $this->workflow->submit($officeHead, $monthly);
        $this->workflow->approve($this->supervisor(), $monthly);

        $annual = AnnualReport::factory()->create(['department_id' => $this->office->id, 'ethiopian_fiscal_year' => 2018]);
        $this->workflow->submit($officeHead, $annual);

        $snapshot = $annual->fresh()->results_snapshot;
        $this->assertCount(1, $snapshot);
        $this->assertEquals(90, $snapshot[0]['actual']);
        $this->assertEquals(7.5, $snapshot[0]['achievement']);
    }

    private function weeklyReportWithEntry(?Department $office = null): WeeklyProgressReport
    {
        $report = WeeklyProgressReport::factory()->create(['department_id' => ($office ?? $this->office)->id]);
        WeeklyProgressEntry::factory()->create(['weekly_progress_report_id' => $report->id, 'actual_value' => 10]);

        return $report;
    }
}
