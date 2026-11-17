<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesDomainData;
use Tests\TestCase;

class PortalRoutingTest extends TestCase
{
    use CreatesDomainData, RefreshDatabase;

    public function test_root_redirects_guests_to_the_admin_login(): void
    {
        $this->get('/')->assertRedirect('/admin/login');
    }

    public function test_admin_dashboard_renders_for_an_administrator(): void
    {
        $this->actingAs($this->makeAdmin())
            ->withoutVite()
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Operations dashboard');
    }

    public function test_company_dashboard_renders_for_a_company_user(): void
    {
        $company = $this->makeCompany();
        $user = $this->makeCompanyUser($company);

        $this->actingAs($user)
            ->withoutVite()
            ->get('/company/dashboard')
            ->assertOk()
            ->assertSee('Book seats without leaving your desk');
    }

    public function test_company_domain_root_redirects_to_the_company_login(): void
    {
        $this->get('http://company.localhost/')
            ->assertRedirect('http://company.localhost/company/login');
    }

    public function test_admin_routes_are_rejected_on_the_company_domain(): void
    {
        $this->get('http://company.localhost/admin/dashboard')->assertNotFound();
    }

    public function test_company_routes_are_rejected_on_the_admin_domain(): void
    {
        $this->get('http://admin.localhost/company/dashboard')->assertNotFound();
    }
}
