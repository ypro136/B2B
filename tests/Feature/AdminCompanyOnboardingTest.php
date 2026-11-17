<?php

namespace Tests\Feature;

use App\Models\AdminPermission;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesDomainData;
use Tests\TestCase;

class AdminCompanyOnboardingTest extends TestCase
{
    use CreatesDomainData, RefreshDatabase;

    public function test_super_admin_can_create_a_company(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->post(route('admin.companies.store'), [
            'name' => 'Northwind Freight',
            'contact' => 'ops@northwind.example',
            'seat_limit' => 15,
            'is_active' => '1',
        ]);

        $company = Company::query()->where('name', 'Northwind Freight')->firstOrFail();

        $response->assertRedirect(route('admin.companies.edit', $company));
        $this->assertSame('ops@northwind.example', $company->contact);
        $this->assertSame(15, $company->seat_limit);
        $this->assertTrue($company->is_active);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'company.created',
            'auditable_id' => $company->id,
        ]);
    }

    public function test_regular_admin_without_permission_cannot_create_a_company(): void
    {
        $admin = $this->makeAdmin(Role::ADMIN);

        $this->actingAs($admin)->post(route('admin.companies.store'), [
            'name' => 'Northwind Freight',
            'seat_limit' => 15,
            'is_active' => '1',
        ])->assertForbidden();

        $this->assertDatabaseMissing('companies', ['name' => 'Northwind Freight']);
    }

    public function test_regular_admin_with_manage_companies_can_create_a_company(): void
    {
        $admin = $this->makeAdmin(Role::ADMIN);
        $this->grantPermission($admin, AdminPermission::MANAGE_COMPANIES);

        $this->actingAs($admin)->post(route('admin.companies.store'), [
            'name' => 'Northwind Freight',
            'seat_limit' => 15,
            'is_active' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('companies', ['name' => 'Northwind Freight']);
    }

    public function test_company_names_must_be_unique(): void
    {
        $this->makeCompany('Acme Logistics');
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->post(route('admin.companies.store'), [
            'name' => 'Acme Logistics',
            'seat_limit' => 10,
            'is_active' => '1',
        ])->assertSessionHasErrors('name');

        $this->assertDatabaseCount('companies', 1);
    }

    public function test_super_admin_can_add_a_company_user(): void
    {
        $company = $this->makeCompany('Acme Logistics', 10);
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->post(route('admin.companies.users.store', $company), [
            'name' => 'Ada Dispatcher',
            'email' => 'ada@acme.example',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => Role::DISPATCHER,
        ]);

        $response->assertRedirect(route('admin.companies.edit', $company));

        $user = User::query()->where('email', 'ada@acme.example')->firstOrFail();

        $this->assertSame($company->id, $user->company_id);
        $this->assertTrue($user->is_active);
        $this->assertTrue($user->hasRole(Role::DISPATCHER));
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'company_user.created',
            'auditable_id' => $user->id,
        ]);
    }

    public function test_a_company_cannot_exceed_two_user_accounts(): void
    {
        $company = $this->makeCompany();
        $this->makeCompanyUser($company);
        $this->makeCompanyUser($company);
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->post(route('admin.companies.users.store', $company), [
            'name' => 'Third User',
            'email' => 'third@acme.example',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => Role::CLIENT,
        ])->assertSessionHasErrors('users');

        $this->assertSame(2, $company->users()->count());
        $this->assertDatabaseMissing('users', ['email' => 'third@acme.example']);
    }

    public function test_duplicate_emails_are_rejected(): void
    {
        $company = $this->makeCompany();
        $existing = $this->makeCompanyUser($company);
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->post(route('admin.companies.users.store', $company), [
            'name' => 'Duplicate Email',
            'email' => $existing->email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => Role::CLIENT,
        ])->assertSessionHasErrors('email');

        $this->assertSame(1, $company->users()->count());
    }

    public function test_only_company_roles_can_be_assigned(): void
    {
        $company = $this->makeCompany();
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->post(route('admin.companies.users.store', $company), [
            'name' => 'Escalated User',
            'email' => 'escalated@acme.example',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => Role::SUPER_ADMIN,
        ])->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', ['email' => 'escalated@acme.example']);
    }

    public function test_manage_companies_permission_is_required_to_add_company_users(): void
    {
        $company = $this->makeCompany();
        $admin = $this->makeAdmin(Role::ADMIN);

        $this->actingAs($admin)->post(route('admin.companies.users.store', $company), [
            'name' => 'Ada Dispatcher',
            'email' => 'ada@acme.example',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => Role::DISPATCHER,
        ])->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'ada@acme.example']);
    }

    public function test_super_admin_can_deactivate_and_reactivate_a_company_user(): void
    {
        $company = $this->makeCompany();
        $user = $this->makeCompanyUser($company);
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->post(route('admin.companies.users.toggle', [$company, $user]))
            ->assertRedirect();

        $this->assertFalse($user->fresh()->is_active);

        $this->actingAs($admin)
            ->post(route('admin.companies.users.toggle', [$company, $user]))
            ->assertRedirect();

        $this->assertTrue($user->fresh()->is_active);
    }

    public function test_admins_cannot_manage_users_of_another_company(): void
    {
        $company = $this->makeCompany('Acme Logistics');
        $otherCompany = $this->makeCompany('Beta Freight');
        $user = $this->makeCompanyUser($company);
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->post(route('admin.companies.users.toggle', [$otherCompany, $user]))
            ->assertNotFound();

        $this->assertTrue($user->fresh()->is_active);
    }

    public function test_company_create_page_renders_for_super_admins(): void
    {
        $this->actingAs($this->makeAdmin())
            ->withoutVite()
            ->get(route('admin.companies.create'))
            ->assertOk()
            ->assertSee('New client company');
    }

    public function test_company_management_pages_render_for_super_admins(): void
    {
        $company = $this->makeCompany();
        $user = $this->makeCompanyUser($company);

        $this->actingAs($this->makeAdmin());

        $this->withoutVite()
            ->get(route('admin.companies.index'))
            ->assertOk()
            ->assertSee('Client companies')
            ->assertSee('Add company');

        $this->withoutVite()
            ->get(route('admin.companies.edit', $company))
            ->assertOk()
            ->assertSee($company->name)
            ->assertSee($user->email)
            ->assertSee('Add user account');
    }

    private function grantPermission(User $admin, string $feature): void
    {
        AdminPermission::query()->create([
            'admin_user_id' => $admin->id,
            'feature' => $feature,
            'granted_by' => $this->makeAdmin()->id,
            'granted_at' => now(),
        ]);
    }
}
