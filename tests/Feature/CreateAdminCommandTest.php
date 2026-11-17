<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_command_creates_a_super_administrator(): void
    {
        $this->artisan('admin:create', [
            'email' => 'owner@example.com',
            '--name' => 'Platform Owner',
            '--password' => 'secret-pass',
        ])->assertSuccessful();

        $admin = User::query()->where('email', 'owner@example.com')->firstOrFail();

        $this->assertSame('Platform Owner', $admin->name);
        $this->assertTrue($admin->is_active);
        $this->assertTrue($admin->isSuperAdmin());
        $this->assertTrue(Hash::check('secret-pass', $admin->password));
    }

    public function test_the_command_derives_a_name_from_the_email(): void
    {
        $this->artisan('admin:create', [
            'email' => 'jane.doe@example.com',
            '--password' => 'secret-pass',
        ])->assertSuccessful();

        $admin = User::query()->where('email', 'jane.doe@example.com')->firstOrFail();

        $this->assertSame('Jane Doe', $admin->name);
    }

    public function test_duplicate_emails_are_rejected(): void
    {
        User::factory()->create(['email' => 'owner@example.com']);

        $this->artisan('admin:create', [
            'email' => 'owner@example.com',
            '--password' => 'secret-pass',
        ])->assertExitCode(1);

        $this->assertSame(1, User::query()->where('email', 'owner@example.com')->count());
    }

    public function test_short_passwords_and_invalid_emails_are_rejected(): void
    {
        $this->artisan('admin:create', [
            'email' => 'owner@example.com',
            '--password' => 'short',
        ])->assertExitCode(1);

        $this->artisan('admin:create', [
            'email' => 'not-an-email',
            '--password' => 'secret-pass',
        ])->assertExitCode(1);

        $this->assertSame(0, User::query()->count());
    }
}
