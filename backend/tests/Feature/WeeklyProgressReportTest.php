<?php

namespace Tests\Feature;

use App\Filament\Resources\WeeklyProgressReports\WeeklyProgressReportResource;
use App\Models\AnnualTarget;
use App\Models\Department;
use App\Models\MonthlyPlan;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\WeeklyProgressEntry;
use App\Models\WeeklyProgressReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class WeeklyProgressReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_weekly_report_records_entries_and_audit_history(): void
    {
        $department = Department::factory()->create();
        $author = User::factory()->create();
        $report = WeeklyProgressReport::factory()->create([
            'department_id' => $department->id,
            'submitted_by' => $author->id,
        ]);
        $plan = $this->monthlyPlanFor($department, 2018, 1);
        $entry = WeeklyProgressEntry::factory()->create([
            'weekly_progress_report_id' => $report->id,
            'monthly_plan_id' => $plan->id,
        ]);

        $this->assertTrue($entry->report->is($report));
        $this->assertTrue($report->submittedBy->is($author));
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => $report->getMorphClass(),
            'auditable_id' => $report->id,
            'event' => 'created',
        ]);
    }

    public function test_a_progress_entry_rejects_a_monthly_plan_from_another_department(): void
    {
        $report = WeeklyProgressReport::factory()->create();
        $otherDepartment = Department::factory()->create();
        $otherPlan = $this->monthlyPlanFor($otherDepartment, 2018, 1);

        $this->expectException(ValidationException::class);

        WeeklyProgressEntry::factory()->create([
            'weekly_progress_report_id' => $report->id,
            'monthly_plan_id' => $otherPlan->id,
        ]);
    }

    public function test_a_report_period_cannot_change_after_progress_entries_are_added(): void
    {
        $department = Department::factory()->create();
        $report = WeeklyProgressReport::factory()->create(['department_id' => $department->id]);
        $plan = $this->monthlyPlanFor($department, 2018, 1);
        WeeklyProgressEntry::factory()->create([
            'weekly_progress_report_id' => $report->id,
            'monthly_plan_id' => $plan->id,
        ]);

        $this->expectException(ValidationException::class);

        $report->update(['ethiopian_month' => 2]);
    }

    public function test_a_department_manager_can_only_manage_reports_for_their_department(): void
    {
        $user = $this->userWithPermissions(['reports.view', 'reports.manage']);
        $usersDepartment = Department::factory()->create();
        $otherDepartment = Department::factory()->create();
        $user->departments()->attach($usersDepartment, ['is_primary' => true]);
        $usersReport = WeeklyProgressReport::factory()->create(['department_id' => $usersDepartment->id]);
        $otherReport = WeeklyProgressReport::factory()->create(['department_id' => $otherDepartment->id]);

        $this->actingAs($user);

        $this->assertTrue(WeeklyProgressReportResource::canEdit($usersReport));
        $this->assertFalse(WeeklyProgressReportResource::canEdit($otherReport));
    }

    private function monthlyPlanFor(Department $department, int $fiscalYear, int $month): MonthlyPlan
    {
        $target = AnnualTarget::factory()->create([
            'department_id' => $department->id,
            'fiscal_year' => $fiscalYear,
        ]);

        return MonthlyPlan::factory()->create([
            'annual_target_id' => $target->id,
            'ethiopian_month' => $month,
        ]);
    }

    private function userWithPermissions(array $permissionNames): User
    {
        $permissions = collect($permissionNames)->map(fn (string $name) => Permission::factory()->create(['name' => $name]));
        $role = Role::factory()->create();
        $role->permissions()->attach($permissions);
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }
}
