<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsOrganization;
use Tests\TestCase;

class PermissionScopeTest extends TestCase
{
    use BuildsOrganization;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildOrganization();
    }

    public function test_a_permission_granted_to_another_users_role_is_not_shared(): void
    {
        $this->cityPlanner();
        $officer = $this->reportingOfficer();

        $this->assertFalse($officer->hasPermission('reports.targets.manage'));
        $this->assertFalse($officer->hasOrganizationWideScope());
        $this->assertTrue($officer->hasPermission('reports.manage'));
    }

    public function test_offices_see_themselves_and_offices_below_them_only(): void
    {
        $supervisor = $this->supervisor();
        $officeHead = $this->officeHead();

        $this->assertEqualsCanonicalizing([$this->department->id, $this->office->id], $supervisor->visibleDepartmentIds());
        $this->assertSame([$this->office->id], $officeHead->visibleDepartmentIds());
        $this->assertCount(4, $this->cityPlanner()->visibleDepartmentIds());
    }

    public function test_reviewers_review_subordinate_offices_but_not_their_own(): void
    {
        $this->assertSame([$this->office->id], $this->supervisor()->reviewableDepartmentIds());
        $this->assertSame([], $this->officeHead()->reviewableDepartmentIds());
    }

    public function test_an_office_cannot_be_placed_under_its_own_subordinate(): void
    {
        $this->expectException(ValidationException::class);

        $this->department->update(['parent_id' => $this->office->id]);
    }
}
