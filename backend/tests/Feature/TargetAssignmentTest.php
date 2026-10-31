<?php

namespace Tests\Feature;

use App\Models\AnnualTarget;
use App\Models\WeeklyProgressEntry;
use App\Models\WeeklyProgressReport;
use App\Services\TargetAssignment;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsOrganization;
use Tests\TestCase;

class TargetAssignmentTest extends TestCase
{
    use BuildsOrganization;
    use RefreshDatabase;

    private TargetAssignment $assignment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildOrganization();
        $this->assignment = app(TargetAssignment::class);
    }

    public function test_revisions_keep_the_original_value_and_record_who_and_why(): void
    {
        $planner = $this->cityPlanner();
        $target = AnnualTarget::factory()->create(['department_id' => $this->office->id, 'target_value' => 1200]);

        $this->assignment->revise($planner, $target, 1100, 'Budget reduced in mid-year review.');
        $target->refresh();

        $this->assertEquals(1100, $target->target_value);
        $this->assertEquals(1200, $target->original_target_value);
        $this->assertDatabaseHas('annual_target_revisions', [
            'annual_target_id' => $target->id,
            'revised_by' => $planner->id,
            'previous_value' => 1200,
            'new_value' => 1100,
        ]);
    }

    public function test_a_target_value_cannot_be_changed_without_a_revision(): void
    {
        $target = AnnualTarget::factory()->create(['target_value' => 1200]);

        $this->expectException(ValidationException::class);

        $target->update(['target_value' => 1000]);
    }

    public function test_an_office_cannot_revise_its_own_assigned_target(): void
    {
        $target = AnnualTarget::factory()->create(['department_id' => $this->office->id]);

        $this->assertFalse($this->assignment->canEdit($this->officeHead(), $target));
        $this->expectException(AuthorizationException::class);

        $this->assignment->revise($this->officeHead(), $target, 1, 'Lower is easier.');
    }

    public function test_supervisors_distribute_only_to_their_direct_subordinate_offices(): void
    {
        $supervisor = $this->supervisor();
        $target = AnnualTarget::factory()->create(['department_id' => $this->department->id, 'target_value' => 1200]);

        $this->assertSame([$this->office->id => 'Sanitation office'], $this->assignment->distributionOptions($supervisor, $target));

        $this->assignment->distribute($supervisor, $target, [['department_id' => $this->office->id, 'target_value' => 700]]);

        $child = $target->children()->firstOrFail();
        $this->assertSame($this->office->id, $child->department_id);
        $this->assertTrue($child->assignedBy->is($supervisor));
        $this->assertTrue($this->assignment->canEdit($supervisor, $child));
        $this->assertFalse($this->assignment->canEdit($supervisor, $target));

        $secondTarget = AnnualTarget::factory()->create(['department_id' => $this->department->id]);

        $this->expectException(ValidationException::class);

        $this->assignment->distribute($supervisor, $secondTarget, [['department_id' => $this->otherOffice->id, 'target_value' => 100]]);
    }

    public function test_the_responsible_office_allocates_months_and_cannot_remove_reported_months(): void
    {
        $officeHead = $this->officeHead();
        $target = $this->targetWithPlans($this->office, 1200, [1 => 100]);

        $this->assertFalse($this->assignment->canAllocate($this->cityPlanner(), $target));

        $this->assignment->allocate($officeHead, $target, [
            1 => ['planned_value' => 80, 'planned_activities' => null],
            2 => ['planned_value' => 90, 'planned_activities' => 'Open second service desk'],
        ]);

        $this->assertEquals([1 => 80, 2 => 90], $target->monthlyPlans()->pluck('planned_value', 'ethiopian_month')->map(fn ($v) => (float) $v)->all());

        $report = WeeklyProgressReport::factory()->create(['department_id' => $this->office->id, 'ethiopian_month' => 2]);
        WeeklyProgressEntry::factory()->create(['weekly_progress_report_id' => $report->id, 'monthly_plan_id' => $target->monthlyPlans()->where('ethiopian_month', 2)->value('id')]);

        $this->expectException(ValidationException::class);

        $this->assignment->allocate($officeHead, $target, [1 => ['planned_value' => 80, 'planned_activities' => null]]);
    }
}
