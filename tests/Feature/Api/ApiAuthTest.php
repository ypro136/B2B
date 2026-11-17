<?php

namespace Tests\Feature\Api;

use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesDomainData;
use Tests\TestCase;

class ApiAuthTest extends TestCase
{
    use CreatesDomainData, RefreshDatabase;

    public function test_company_user_can_obtain_a_token(): void
    {
        $company = $this->makeCompany('Acme Logistics', 10);
        $user = $this->makeCompanyUser($company);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'tests',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'token',
                'token_type',
                'user' => ['id', 'name', 'email', 'company' => ['id', 'name', 'seat_limit'], 'roles'],
            ])
            ->assertJsonPath('user.email', $user->email)
            ->assertJsonPath('user.company.id', $company->id);

        $this->assertSame(1, $user->tokens()->count());
        $this->assertSame('tests', $user->tokens()->first()->name);
    }

    public function test_inactive_company_user_cannot_obtain_a_token(): void
    {
        $company = $this->makeCompany();
        $user = $this->makeCompanyUser($company);
        $user->update(['is_active' => false]);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertStatus(422)->assertJsonValidationErrors('email');

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_company_users_of_inactive_companies_cannot_obtain_a_token(): void
    {
        $company = $this->makeCompany();
        $user = $this->makeCompanyUser($company);
        $company->update(['is_active' => false]);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_administrators_cannot_use_the_company_api(): void
    {
        $admin = $this->makeAdmin();

        $this->postJson('/api/login', [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_admin_tokens_cannot_access_company_endpoints(): void
    {
        $admin = $this->makeAdmin();
        $token = $admin->createToken('admin-test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/trips')
            ->assertForbidden();
    }

    public function test_guests_cannot_access_protected_endpoints(): void
    {
        $this->getJson('/api/trips')->assertUnauthorized();
        $this->getJson('/api/bookings')->assertUnauthorized();
        $this->getJson('/api/seat-requests')->assertUnauthorized();
    }

    public function test_tokens_can_be_used_and_revoked(): void
    {
        $company = $this->makeCompany();
        $user = $this->makeCompanyUser($company);

        $token = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->json('token');

        $this->withToken($token)
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('user.email', $user->email);

        $this->withToken($token)->postJson('/api/logout')->assertOk();

        $this->assertSame(0, $user->tokens()->count());

        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/user')->assertUnauthorized();
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_deactivating_a_user_rejects_their_existing_token(): void
    {
        $company = $this->makeCompany();
        $user = $this->makeCompanyUser($company);

        $token = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->json('token');

        $user->update(['is_active' => false]);

        $this->withToken($token)->getJson('/api/trips')->assertForbidden();
    }

    public function test_login_attempts_are_rate_limited(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/login', [
                'email' => 'nobody@example.com',
                'password' => 'wrong-password',
            ])->assertStatus(422);
        }

        $this->postJson('/api/login', [
            'email' => 'nobody@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }

    public function test_company_roles_are_returned_with_the_user(): void
    {
        $company = $this->makeCompany();
        $user = $this->makeCompanyUser($company, Role::DISPATCHER);

        $token = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->json('token');

        $this->withToken($token)
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('user.roles.0', Role::DISPATCHER);
    }
}
