<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_have_a_department_and_role(): void
    {
        $user = User::factory()->create();
        $department = Department::factory()->create();
        $role = Role::factory()->create();

        $user->departments()->attach($department, ['is_primary' => true]);
        $user->roles()->attach($role);

        $this->assertTrue($user->departments->contains($department));
        $this->assertTrue($user->roles->contains($role));
        $this->assertTrue((bool) $user->departments->first()->pivot->is_primary);
    }

    public function test_permissions_are_checked_through_assigned_roles(): void
    {
        $user = User::factory()->create();
        $role = Role::factory()->create();
        $permission = Permission::factory()->create(['name' => 'reports.view']);

        $role->permissions()->attach($permission);
        $user->roles()->attach($role);

        $this->assertTrue($user->hasPermission('reports.view'));
        $this->assertFalse($user->hasPermission('attendance.manage'));
    }

    public function test_changes_are_recorded_without_sensitive_user_values(): void
    {
        $user = User::factory()->create();
        $department = Department::factory()->create();

        $auditLog = AuditLog::where('auditable_type', $user->getMorphClass())
            ->where('auditable_id', $user->id)
            ->where('event', 'created')
            ->firstOrFail();

        $this->assertArrayNotHasKey('password', $auditLog->new_values);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => $department->getMorphClass(),
            'auditable_id' => $department->id,
            'event' => 'created',
        ]);
    }
}
