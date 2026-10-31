<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DatabaseSeederAdministratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_system_administrator_from_configured_credentials_without_resetting_existing_passwords(): void
    {
        config([
            'auth.seed_admin.name' => 'Town Administrator',
            'auth.seed_admin.email' => 'admin@example.test',
            'auth.seed_admin.password' => 'a-unique-password-with-24-chars',
        ]);

        $this->seed();

        $administrator = User::where('email', 'admin@example.test')->firstOrFail();

        $this->assertSame('Town Administrator', $administrator->name);
        $this->assertTrue(Hash::check('a-unique-password-with-24-chars', $administrator->password));
        $this->assertNotNull($administrator->email_verified_at);
        $this->assertTrue($administrator->roles()->where('name', 'system_admin')->exists());

        config(['auth.seed_admin.password' => 'another-unique-password-with-24-chars']);

        $this->seed();

        $administrator->refresh();

        $this->assertTrue(Hash::check('a-unique-password-with-24-chars', $administrator->password));
    }

    public function test_it_does_not_create_an_administrator_without_configured_credentials(): void
    {
        config([
            'auth.seed_admin.email' => null,
            'auth.seed_admin.password' => null,
        ]);

        $this->seed();

        $this->assertDatabaseCount('users', 0);
    }
}
