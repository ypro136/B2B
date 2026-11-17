<?php

namespace Tests\Feature;

use App\Models\AdminPermission;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Role;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesDomainData;
use Tests\TestCase;

class AdminTabConsolidationTest extends TestCase
{
    use CreatesDomainData, RefreshDatabase;

    public function test_record_payment_page_prefills_from_pending_payment_booking(): void
    {
        $admin = $this->makeAdmin();
        $company = $this->makeCompany();
        $user = $this->makeCompanyUser($company);
        $trip = $this->makeTrip(['standard_price' => 100]);

        $booking = app(BookingService::class)->book($company, $trip, $user, 2);

        $response = $this->actingAs($admin)->withoutVite()->get(route('admin.payments.create'));

        $response->assertOk();
        $response->assertSee('booking_shortcut', false);
        $response->assertSee('data-company-id="'.$company->id.'"', false);
        $response->assertSee('200.00', false);
    }

    public function test_shortcut_excludes_fully_settled_bookings(): void
    {
        $admin = $this->makeAdmin();
        $company = $this->makeCompany();
        $user = $this->makeCompanyUser($company);
        $trip = $this->makeTrip(['standard_price' => 100]);

        $booking = app(BookingService::class)->book($company, $trip, $user, 2);
        $booking->update(['settled_amount' => $booking->total_amount, 'settlement_status' => Booking::SETTLEMENT_FULL]);

        $response = $this->actingAs($admin)->withoutVite()->get(route('admin.payments.create'));

        $response->assertOk();
        $response->assertDontSee('data-company-id="'.$company->id.'"', false);
    }

    public function test_legacy_tab_urls_redirect_into_merged_tabs(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->get('/admin/documents')->assertStatus(301)
            ->assertRedirect(route('admin.payments.documents.index'));
        $this->actingAs($admin)->get('/admin/documents/monthly')->assertStatus(301)
            ->assertRedirect(route('admin.payments.documents.monthly-form'));
        $this->actingAs($admin)->get('/admin/periods')->assertStatus(301)
            ->assertRedirect(route('admin.reports.finance').'#periods');
        $this->actingAs($admin)->get('/admin/permissions')->assertStatus(301)
            ->assertRedirect(route('admin.settings.index').'#permissions');
    }

    public function test_admin_with_manage_admins_can_add_an_admin(): void
    {
        $superAdmin = $this->makeAdmin();
        $manager = $this->makeAdmin(Role::ADMIN);

        AdminPermission::query()->create([
            'admin_user_id' => $manager->id,
            'feature' => AdminPermission::MANAGE_ADMINS,
            'granted_by' => $superAdmin->id,
            'granted_at' => now(),
        ]);

        $this->actingAs($manager)
            ->post(route('admin.settings.admins.store'), [
                'name' => 'New Admin',
                'email' => 'new.admin@example.com',
                'password' => 'password-123',
                'password_confirmation' => 'password-123',
                'role' => Role::ADMIN,
            ])
            ->assertRedirect();

        $created = User::query()->where('email', 'new.admin@example.com')->firstOrFail();
        $this->assertNull($created->company_id);
        $this->assertTrue($created->is_active);
        $this->assertTrue($created->hasRole(Role::ADMIN));
        $this->assertFalse($created->isSuperAdmin());
        $this->assertFalse($created->hasFeature(AdminPermission::RECORD_PAYMENTS));
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'admin.created',
            'actor_id' => $manager->id,
        ]);
    }

    public function test_manage_admins_holder_can_assign_super_admin_role(): void
    {
        $superAdmin = $this->makeAdmin();
        $manager = $this->makeAdmin(Role::ADMIN);

        AdminPermission::query()->create([
            'admin_user_id' => $manager->id,
            'feature' => AdminPermission::MANAGE_ADMINS,
            'granted_by' => $superAdmin->id,
            'granted_at' => now(),
        ]);

        $this->actingAs($manager)
            ->post(route('admin.settings.admins.store'), [
                'name' => 'Second Super',
                'email' => 'second.super@example.com',
                'password' => 'password-123',
                'password_confirmation' => 'password-123',
                'role' => Role::SUPER_ADMIN,
            ])
            ->assertRedirect();

        $this->assertTrue(User::query()->where('email', 'second.super@example.com')->firstOrFail()->isSuperAdmin());
    }

    public function test_admin_without_manage_admins_cannot_add_an_admin(): void
    {
        $regular = $this->makeAdmin(Role::ADMIN);

        $this->actingAs($regular)->get(route('admin.settings.index'))->assertForbidden();

        $this->actingAs($regular)
            ->post(route('admin.settings.admins.store'), [
                'name' => 'Nope',
                'email' => 'nope@example.com',
                'password' => 'password-123',
                'password_confirmation' => 'password-123',
                'role' => Role::ADMIN,
            ])
            ->assertForbidden();
    }

    public function test_manage_admins_holder_cannot_grant_permissions(): void
    {
        $superAdmin = $this->makeAdmin();
        $manager = $this->makeAdmin(Role::ADMIN);

        AdminPermission::query()->create([
            'admin_user_id' => $manager->id,
            'feature' => AdminPermission::MANAGE_ADMINS,
            'granted_by' => $superAdmin->id,
            'granted_at' => now(),
        ]);

        $this->actingAs($manager)
            ->post(route('admin.settings.permissions.store'), [
                'admin_user_id' => $manager->id,
                'feature' => AdminPermission::RECORD_PAYMENTS,
            ])
            ->assertForbidden();
    }

    public function test_manage_admins_holder_sees_settings_page_with_add_admin_only(): void
    {
        $superAdmin = $this->makeAdmin();
        $manager = $this->makeAdmin(Role::ADMIN);

        AdminPermission::query()->create([
            'admin_user_id' => $manager->id,
            'feature' => AdminPermission::MANAGE_ADMINS,
            'granted_by' => $superAdmin->id,
            'granted_at' => now(),
        ]);

        $response = $this->actingAs($manager)->withoutVite()->get(route('admin.settings.index'));

        $response->assertOk();
        $response->assertSee('Add administrator');
        $response->assertDontSee('Platform settings');
        $response->assertDontSee('Admin permissions');
    }

    public function test_add_admin_rejects_duplicate_email_and_weak_password(): void
    {
        $superAdmin = $this->makeAdmin();

        $this->actingAs($superAdmin)
            ->post(route('admin.settings.admins.store'), [
                'name' => 'Dupe',
                'email' => $superAdmin->email,
                'password' => 'password-123',
                'password_confirmation' => 'password-123',
                'role' => Role::ADMIN,
            ])
            ->assertSessionHasErrors('email');

        $this->actingAs($superAdmin)
            ->post(route('admin.settings.admins.store'), [
                'name' => 'Weak',
                'email' => 'weak@example.com',
                'password' => 'short',
                'password_confirmation' => 'short',
                'role' => Role::ADMIN,
            ])
            ->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'weak@example.com']);
    }
}
