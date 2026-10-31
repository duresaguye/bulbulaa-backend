<?php

namespace Tests\Feature;

use App\Filament\Resources\AnnualTargets\AnnualTargetResource;
use App\Filament\Resources\MonthlyPlans\MonthlyPlanResource;
use App\Models\AnnualTarget;
use App\Models\Department;
use App\Models\MonthlyPlan;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportingFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_annual_target_belongs_to_a_department_and_records_changes(): void
    {
        $department = Department::factory()->create();
        $target = AnnualTarget::factory()->create(['department_id' => $department->id]);

        $this->assertTrue($target->department->is($department));
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => $target->getMorphClass(),
            'auditable_id' => $target->id,
            'event' => 'created',
        ]);
    }

    public function test_a_monthly_plan_is_unique_for_an_annual_target_and_ethiopian_month(): void
    {
        $target = AnnualTarget::factory()->create();
        MonthlyPlan::factory()->create(['annual_target_id' => $target->id, 'ethiopian_month' => 1]);

        $this->expectException(UniqueConstraintViolationException::class);

        MonthlyPlan::factory()->create(['annual_target_id' => $target->id, 'ethiopian_month' => 1]);
    }

    public function test_town_leadership_can_manage_annual_targets_but_not_monthly_plans(): void
    {
        $user = $this->userWithPermissions(['reports.view', 'reports.targets.manage']);
        $monthlyPlan = MonthlyPlan::factory()->create();

        $this->actingAs($user);

        $this->assertTrue(AnnualTargetResource::canCreate());
        $this->assertFalse(MonthlyPlanResource::canCreate());
        $this->assertFalse(MonthlyPlanResource::canEdit($monthlyPlan));
    }

    public function test_a_department_manager_can_only_manage_monthly_plans_for_their_department(): void
    {
        $user = $this->userWithPermissions(['reports.view', 'reports.manage']);
        $usersDepartment = Department::factory()->create();
        $otherDepartment = Department::factory()->create();
        $user->departments()->attach($usersDepartment, ['is_primary' => true]);
        $usersPlan = MonthlyPlan::factory()->create([
            'annual_target_id' => AnnualTarget::factory()->create(['department_id' => $usersDepartment->id])->id,
        ]);
        $otherPlan = MonthlyPlan::factory()->create([
            'annual_target_id' => AnnualTarget::factory()->create(['department_id' => $otherDepartment->id])->id,
        ]);

        $this->actingAs($user);

        $this->assertTrue(MonthlyPlanResource::canEdit($usersPlan));
        $this->assertFalse(MonthlyPlanResource::canEdit($otherPlan));
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
