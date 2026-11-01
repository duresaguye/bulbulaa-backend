<?php

namespace Database\Seeders;

use App\Models\AnnualTarget;
use App\Models\Department;
use App\Models\MonthlyPlan;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use InvalidArgumentException;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $permissions = collect([
            ['name' => 'users.manage', 'label' => 'Manage users'],
            ['name' => 'departments.manage', 'label' => 'Manage departments'],
            ['name' => 'roles.manage', 'label' => 'Manage roles and permissions'],
            ['name' => 'audit_logs.view', 'label' => 'View audit history'],
            ['name' => 'reports.view', 'label' => 'View department reports'],
            ['name' => 'reports.manage', 'label' => 'Manage department reports'],
            ['name' => 'reports.targets.manage', 'label' => 'Manage annual reporting targets'],
            ['name' => 'reports.submit', 'label' => 'Submit office reports for review'],
            ['name' => 'reports.review', 'label' => 'Approve or return reports from subordinate offices'],
            ['name' => 'targets.distribute', 'label' => 'Distribute targets to subordinate offices'],
            ['name' => 'cms.manage', 'label' => 'Manage public content'],
            ['name' => 'citizen_reports.manage', 'label' => 'Handle messages from the public'],
        ])->mapWithKeys(function (array $attributes): array {
            $permission = Permission::firstOrCreate(
                ['name' => $attributes['name']],
                ['label' => $attributes['label']],
            );

            return [$permission->name => $permission];
        });

        $roles = [
            'system_admin' => [
                'label' => 'System administrator',
                'permissions' => $permissions->keys()->all(),
            ],
            'town_leadership' => [
                'label' => 'Town leadership',
                'permissions' => ['departments.manage', 'reports.view', 'reports.targets.manage', 'reports.review', 'audit_logs.view', 'citizen_reports.manage'],
            ],
            'supervisor' => [
                'label' => 'Supervising official',
                'permissions' => ['reports.view', 'reports.manage', 'reports.submit', 'reports.review', 'targets.distribute'],
            ],
            'department_manager' => [
                'label' => 'Department manager',
                'permissions' => ['reports.view', 'reports.manage', 'reports.submit'],
            ],
            'reporting_officer' => [
                'label' => 'Reporting officer',
                'permissions' => ['reports.view', 'reports.manage'],
            ],
            'content_manager' => [
                'label' => 'Content manager',
                'permissions' => ['cms.manage', 'citizen_reports.manage'],
            ],
        ];

        foreach ($roles as $name => $attributes) {
            $role = Role::firstOrCreate(
                ['name' => $name],
                ['label' => $attributes['label']],
            );

            $role->permissions()->sync(
                collect($attributes['permissions'])->map(fn (string $permission) => $permissions[$permission]->id)->all(),
            );
        }

        $this->seedSystemAdministrator();

        $leadership = Department::firstOrCreate(['code' => 'LEAD'], ['name' => 'Town leadership']);

        foreach ([
            ['name' => 'Human resources', 'code' => 'HR', 'parent' => 'LEAD'],
            ['name' => 'Finance', 'code' => 'FIN', 'parent' => 'LEAD'],
            ['name' => 'Public services', 'code' => 'SERV', 'parent' => 'LEAD'],
            ['name' => 'Sanitation office', 'code' => 'SAN', 'parent' => 'SERV'],
        ] as $department) {
            $parent = $department['parent'] === 'LEAD' ? $leadership : Department::where('code', $department['parent'])->firstOrFail();

            /** The hierarchy is only set for newly created offices so changes made in the admin panel are kept. */
            Department::firstOrCreate(
                ['code' => $department['code']],
                ['name' => $department['name'], 'parent_id' => $parent->id],
            );
        }

        $publicServices = Department::where('code', 'SERV')->firstOrFail();
        $target = AnnualTarget::firstOrCreate(
            [
                'department_id' => $publicServices->id,
                'fiscal_year' => 2018,
                'title' => 'Improve household waste collection coverage',
            ],
            [
                'description' => 'Fictional development target for demonstrations only.',
                'measurement_unit' => 'Households',
                'aggregation' => 'sum',
                'target_value' => 1200,
                'original_target_value' => 1200,
                'is_active' => true,
            ],
        );

        MonthlyPlan::firstOrCreate(
            ['annual_target_id' => $target->id, 'ethiopian_month' => 1],
            [
                'planned_value' => 100,
                'planned_activities' => 'Fictional planning activity for demonstrations only.',
            ],
        );
    }

    private function seedSystemAdministrator(): void
    {
        $name = config('auth.seed_admin.name') ?: 'Bulbulaa Administrator';
        $email = config('auth.seed_admin.email');
        $password = config('auth.seed_admin.password');

        if (blank($email) && blank($password)) {
            $this->command?->warn('No administrator was created. Set ADMIN_EMAIL and ADMIN_PASSWORD in .env, then run db:seed.');

            return;
        }

        if (blank($email) || blank($password)) {
            throw new InvalidArgumentException('Set both ADMIN_EMAIL and ADMIN_PASSWORD to seed the system administrator.');
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('ADMIN_EMAIL must be a valid email address.');
        }

        if (mb_strlen($password) < 16) {
            throw new InvalidArgumentException('ADMIN_PASSWORD must be at least 16 characters long.');
        }

        $administrator = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => $password,
                'email_verified_at' => now(),
            ],
        );

        $administrator->roles()->syncWithoutDetaching([
            Role::where('name', 'system_admin')->firstOrFail()->id,
        ]);

        $this->command?->info("System administrator ready: {$administrator->email}");
    }
}
