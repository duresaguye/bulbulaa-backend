<?php

namespace Tests\Concerns;

use App\Models\AnnualTarget;
use App\Models\Department;
use App\Models\MonthlyPlan;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;

/**
 * A fictional three-level organisation: city administration → department → office.
 */
trait BuildsOrganization
{
    protected Department $city;

    protected Department $department;

    protected Department $office;

    protected Department $otherOffice;

    protected function buildOrganization(): void
    {
        $this->city = Department::factory()->create(['name' => 'City administration']);
        $this->department = Department::factory()->create(['name' => 'Public services', 'parent_id' => $this->city->id]);
        $this->office = Department::factory()->create(['name' => 'Sanitation office', 'parent_id' => $this->department->id]);
        $this->otherOffice = Department::factory()->create(['name' => 'Finance', 'parent_id' => $this->city->id]);
    }

    /**
     * @param  list<string>  $permissionNames
     */
    protected function userWith(array $permissionNames, ?Department $department = null): User
    {
        $permissions = collect($permissionNames)->map(fn (string $name) => Permission::firstOrCreate(['name' => $name], ['label' => $name]));
        $role = Role::factory()->create();
        $role->permissions()->attach($permissions);

        $user = User::factory()->create();
        $user->roles()->attach($role);

        if ($department !== null) {
            $user->departments()->attach($department, ['is_primary' => true]);
        }

        return $user;
    }

    protected function cityPlanner(): User
    {
        return $this->userWith(['reports.view', 'reports.targets.manage', 'reports.review', 'departments.manage'], $this->city);
    }

    protected function supervisor(): User
    {
        return $this->userWith(['reports.view', 'reports.manage', 'reports.submit', 'reports.review', 'targets.distribute'], $this->department);
    }

    protected function officeHead(?Department $office = null): User
    {
        return $this->userWith(['reports.view', 'reports.manage', 'reports.submit'], $office ?? $this->office);
    }

    protected function reportingOfficer(): User
    {
        return $this->userWith(['reports.view', 'reports.manage'], $this->office);
    }

    /**
     * @param  array<int, float>  $monthlyValues  planned value keyed by Ethiopian month
     */
    protected function targetWithPlans(Department $department, float $annual, array $monthlyValues, string $aggregation = 'sum', int $year = 2018): AnnualTarget
    {
        $target = AnnualTarget::factory()->create([
            'department_id' => $department->id,
            'fiscal_year' => $year,
            'target_value' => $annual,
            'aggregation' => $aggregation,
            'measurement_unit' => $aggregation === 'sum' ? 'Requests' : 'percent',
        ]);

        foreach ($monthlyValues as $month => $value) {
            MonthlyPlan::factory()->create([
                'annual_target_id' => $target->id,
                'ethiopian_month' => $month,
                'planned_value' => $value,
            ]);
        }

        return $target->load('monthlyPlans');
    }
}
