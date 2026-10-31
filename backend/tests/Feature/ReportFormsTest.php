<?php

namespace Tests\Feature;

use App\Enums\ReportStatus;
use App\Filament\Pages\ReviewQueue;
use App\Filament\Resources\AnnualTargets\AnnualTargetResource;
use App\Filament\Resources\AnnualTargets\Pages\AllocateAnnualTarget;
use App\Filament\Resources\AnnualTargets\Pages\EditAnnualTarget;
use App\Filament\Resources\MonthlyReports\Pages\CreateMonthlyReport;
use App\Filament\Resources\WeeklyProgressReports\Pages\CreateWeeklyProgressReport;
use App\Filament\Resources\WeeklyProgressReports\Pages\EditWeeklyProgressReport;
use App\Filament\Resources\WeeklyProgressReports\Pages\ViewWeeklyProgressReport;
use App\Models\MonthlyReport;
use App\Models\WeeklyProgressEntry;
use App\Models\WeeklyProgressReport;
use App\Services\ReportWorkflow;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsOrganization;
use Tests\TestCase;

class ReportFormsTest extends TestCase
{
    use BuildsOrganization;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildOrganization();
    }

    public function test_a_new_weekly_report_lists_every_monthly_target_and_saves_as_a_draft(): void
    {
        $target = $this->targetWithPlans($this->office, 1200, [1 => 80]);
        $this->targetWithPlans($this->office, 300, [1 => 25]);
        $officer = $this->reportingOfficer();

        $this->actingAs($officer);

        $component = Livewire::withQueryParams(['department' => $this->office->id, 'year' => 2018, 'month' => 1, 'week' => 2])
            ->test(CreateWeeklyProgressReport::class)
            ->assertFormSet(['department_id' => $this->office->id, 'ethiopian_month' => 1, 'week_number' => 2])
            ->assertSee('Save draft')
            ->assertDontSee('Save and submit for review');

        $entries = $component->get('data.entries');
        $this->assertCount(2, $entries);

        $component
            ->fillForm([
                'achievements' => 'Cleared the backlog at the main desk.',
                'entries' => collect($entries)->map(fn (array $entry) => [...$entry, 'actual_value' => 20])->all(),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $report = WeeklyProgressReport::firstOrFail();
        $this->assertSame(ReportStatus::Draft, $report->status);
        $this->assertTrue($report->preparedBy->is($officer));
        $this->assertNull($report->submitted_at);
        $this->assertSame(2, $report->entries()->count());
        $this->assertSame(20.0, (float) $report->entries()->where('monthly_plan_id', $target->monthlyPlans->first()->id)->value('actual_value'));
    }

    public function test_an_office_head_can_save_and_submit_in_one_step(): void
    {
        $this->targetWithPlans($this->office, 1200, [1 => 80]);
        $this->actingAs($this->officeHead());

        $component = Livewire::withQueryParams(['department' => $this->office->id, 'year' => 2018, 'month' => 1, 'week' => 1])
            ->test(CreateWeeklyProgressReport::class);

        $component
            ->fillForm([
                'achievements' => 'Done.',
                'entries' => collect($component->get('data.entries'))->map(fn (array $entry) => [...$entry, 'actual_value' => 75])->all(),
            ])
            ->callAction(TestAction::make('createAndSubmit')->schemaComponent('form-actions', schema: 'content'))
            ->assertHasNoFormErrors();

        $this->assertSame(ReportStatus::Submitted, WeeklyProgressReport::firstOrFail()->status);
    }

    public function test_a_duplicate_week_is_rejected_with_a_clear_message(): void
    {
        $this->targetWithPlans($this->office, 1200, [1 => 80]);
        WeeklyProgressReport::factory()->create(['department_id' => $this->office->id, 'ethiopian_fiscal_year' => 2018, 'ethiopian_month' => 1, 'week_number' => 1]);
        $this->actingAs($this->officeHead());

        Livewire::withQueryParams(['department' => $this->office->id, 'year' => 2018, 'month' => 1, 'week' => 1])
            ->test(CreateWeeklyProgressReport::class)
            ->fillForm(['achievements' => 'Done.'])
            ->call('create')
            ->assertHasFormErrors(['week_number']);
    }

    public function test_submitted_reports_cannot_be_opened_for_editing(): void
    {
        $report = WeeklyProgressReport::factory()->create(['department_id' => $this->office->id]);
        WeeklyProgressEntry::factory()->create(['weekly_progress_report_id' => $report->id]);
        $officeHead = $this->officeHead();
        app(ReportWorkflow::class)->submit($officeHead, $report);

        $this->actingAs($officeHead);

        Livewire::test(EditWeeklyProgressReport::class, ['record' => $report->getKey()])->assertForbidden();
    }

    public function test_the_supervisor_returns_a_report_from_its_page_and_it_reaches_the_office(): void
    {
        $report = WeeklyProgressReport::factory()->create(['department_id' => $this->office->id]);
        WeeklyProgressEntry::factory()->create(['weekly_progress_report_id' => $report->id]);
        app(ReportWorkflow::class)->submit($this->officeHead(), $report);
        $supervisor = $this->supervisor();

        $this->actingAs($supervisor);

        Livewire::test(ReviewQueue::class)->assertCanSeeTableRecords([$report]);

        Livewire::test(ViewWeeklyProgressReport::class, ['record' => $report->getKey()])
            ->assertActionVisible('approve')
            ->assertActionHidden('submit')
            ->callAction('return', ['comment' => 'Attach the site photos.']);

        $this->assertSame(ReportStatus::Returned, $report->fresh()->status);

        $this->actingAs($this->officeHead());
        $this->get('/admin')->assertSee('Attach the site photos.');
    }

    public function test_monthly_reports_start_from_weekly_figures_and_require_a_reason_for_shortfalls(): void
    {
        $target = $this->targetWithPlans($this->office, 1200, [1 => 100]);
        foreach ([1 => 40, 2 => 35] as $week => $value) {
            $weekly = WeeklyProgressReport::factory()->create(['department_id' => $this->office->id, 'week_number' => $week, 'achievements' => "Week {$week} work"]);
            WeeklyProgressEntry::factory()->create(['weekly_progress_report_id' => $weekly->id, 'monthly_plan_id' => $target->monthlyPlans->first()->id, 'actual_value' => $value]);
            $weekly->forceFill(['status' => ReportStatus::Submitted])->save();
        }

        $this->actingAs($this->officeHead());

        $component = Livewire::withQueryParams(['department' => $this->office->id, 'year' => 2018, 'month' => 1])
            ->test(CreateMonthlyReport::class);

        $lines = $component->get('data.lines');
        $this->assertEquals(75, collect($lines)->first()['actual_value']);
        $this->assertStringContainsString('Week 1: Week 1 work', $component->get('data.summary'));

        $component->call('create')->assertHasFormErrors();
        $this->assertSame(0, MonthlyReport::count());

        $component
            ->fillForm(['lines' => collect($lines)->map(fn (array $line) => [...$line, 'variance_note' => 'Two staff on leave.'])->all()])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(75.0, (float) MonthlyReport::firstOrFail()->lines()->value('actual_value'));
    }

    public function test_changing_a_target_value_requires_a_reason_and_records_a_revision(): void
    {
        $target = $this->targetWithPlans($this->office, 1200, []);
        $this->actingAs($this->cityPlanner());

        Livewire::test(EditAnnualTarget::class, ['record' => $target->getKey()])
            ->fillForm(['target_value' => 1000])
            ->call('save')
            ->assertHasFormErrors(['revision_reason' => 'required']);

        Livewire::test(EditAnnualTarget::class, ['record' => $target->getKey()])
            ->fillForm(['target_value' => 1000, 'revision_reason' => 'Mid-year budget review.'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEquals(1000, $target->fresh()->target_value);
        $this->assertSame('Mid-year budget review.', $target->revisions()->value('reason'));
    }

    public function test_the_allocation_screen_saves_months_for_the_responsible_office(): void
    {
        $target = $this->targetWithPlans($this->office, 1200, []);
        $this->actingAs($this->officeHead());

        Livewire::test(AllocateAnnualTarget::class, ['record' => $target->getKey()])
            ->set('data.months.1.planned_value', 80)
            ->set('data.months.2.planned_value', 90)
            ->assertSee('1,030 of the annual target is not yet allocated')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(2, $target->monthlyPlans()->count());

        $this->actingAs($this->cityPlanner());
        $this->get(AnnualTargetResource::getUrl('allocate', ['record' => $target]))->assertForbidden();
    }
}
