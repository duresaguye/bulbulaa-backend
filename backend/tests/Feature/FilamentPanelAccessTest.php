<?php

namespace Tests\Feature;

use App\Filament\Resources\AuditLogs\AuditLogResource;
use App\Filament\Resources\Departments\DepartmentResource;
use App\Filament\Resources\Permissions\PermissionResource;
use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Role;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FilamentPanelAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_without_roles_cannot_access_the_admin_panel(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_system_administrators_can_access_the_panel_and_foundation_resources(): void
    {
        $user = User::factory()->create();
        $systemAdministrator = Role::factory()->create(['name' => 'system_admin']);
        $user->roles()->attach($systemAdministrator);

        $this->actingAs($user);

        $this->assertTrue($user->canAccessPanel(Filament::getPanel('admin')));
        $this->assertTrue(DepartmentResource::canViewAny());
        $this->assertTrue(UserResource::canViewAny());
        $this->assertTrue(RoleResource::canViewAny());
        $this->assertTrue(PermissionResource::canViewAny());
        $this->assertTrue(AuditLogResource::canViewAny());
    }
}
