<?php

namespace Tests\Feature;

use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesDomainData;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use CreatesDomainData, RefreshDatabase;

    public function test_super_admin_can_sign_in_to_the_admin_portal(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->post(route('admin.login.store'), [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin);
    }

    public function test_company_user_cannot_sign_in_to_the_admin_portal(): void
    {
        $company = $this->makeCompany();
        $user = $this->makeCompanyUser($company);

        $response = $this->from(route('admin.login'))->post(route('admin.login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('admin.login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_company_user_can_sign_in_to_the_company_portal(): void
    {
        $company = $this->makeCompany();
        $user = $this->makeCompanyUser($company);

        $response = $this->post(route('company.login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('company.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_admin_cannot_sign_in_to_the_company_portal(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->from(route('company.login'))->post(route('company.login.store'), [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('company.login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_inactive_users_cannot_sign_in(): void
    {
        $admin = $this->makeAdmin();
        $admin->update(['is_active' => false]);

        $response = $this->from(route('admin.login'))->post(route('admin.login.store'), [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_guests_are_redirected_to_the_matching_portal_login(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
        $this->get(route('company.dashboard'))->assertRedirect(route('company.login'));
    }

    public function test_a_company_user_is_forbidden_from_the_admin_portal(): void
    {
        $company = $this->makeCompany();
        $user = $this->makeCompanyUser($company);

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_an_admin_is_forbidden_from_the_company_portal(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->get(route('company.dashboard'))
            ->assertForbidden();
    }

    public function test_a_company_cannot_have_more_than_two_users(): void
    {
        $company = $this->makeCompany();

        $this->makeCompanyUser($company);
        $this->makeCompanyUser($company, Role::CLIENT);

        $this->expectException(ValidationException::class);

        $this->makeCompanyUser($company, Role::DISPATCHER);
    }
}
