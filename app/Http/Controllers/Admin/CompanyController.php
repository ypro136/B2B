<?php

namespace App\Http\Controllers\Admin;

use App\Models\AdminPermission;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Role;
use App\Services\CompanySeatLimitService;
use App\Services\QuotaService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CompanyController extends BaseController
{
    /**
     * List the client companies.
     */
    public function index(Request $request): View
    {
        return view('admin::companies.index', [
            'companies' => Company::query()
                ->withCount('users')
                ->orderBy('name')
                ->paginate(15),
            'canManage' => $request->user()->hasFeature(AdminPermission::MANAGE_COMPANIES),
        ]);
    }

    /**
     * Show the company creation form.
     */
    public function create(): View
    {
        return view('admin::companies.create');
    }

    /**
     * Create a client company.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160', 'unique:companies,name'],
            'legal_name' => ['nullable', 'string', 'max:160'],
            'tax_number' => ['nullable', 'string', 'max:60'],
            'address' => ['nullable', 'string', 'max:255'],
            'contact' => ['nullable', 'string', 'max:255'],
            'seat_limit' => ['required', 'integer', 'min:0', 'max:1000000'],
            'partial_payment_allowed' => ['nullable', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ]);

        $company = DB::transaction(function () use ($data, $request) {
            $company = Company::query()->create([
                'name' => $data['name'],
                'legal_name' => $data['legal_name'] ?? null,
                'tax_number' => $data['tax_number'] ?? null,
                'address' => $data['address'] ?? null,
                'contact' => $data['contact'] ?? null,
                'seat_limit' => (int) $data['seat_limit'],
                'partial_payment_allowed' => (bool) ($data['partial_payment_allowed'] ?? false),
                'is_active' => (bool) $data['is_active'],
            ]);

            AuditLog::record($company, 'company.created', $request->user(), null, [
                'name' => $company->name,
                'legal_name' => $company->legal_name,
                'contact' => $company->contact,
                'seat_limit' => $company->seat_limit,
                'partial_payment_allowed' => $company->partial_payment_allowed,
                'is_active' => $company->is_active,
            ]);

            return $company;
        });

        return redirect()
            ->route('admin.companies.edit', $company)
            ->with('status', $company->name.' was created. Add a user account to give them portal access.');
    }

    /**
     * Show the company workspace: quota, users, CRM and history.
     */
    public function edit(Request $request, Company $company): View
    {
        $company->load(['contacts', 'notes.author', 'activities.actor']);

        return view('admin::companies.edit', [
            'company' => $company,
            'seatsInUse' => $company->confirmedSeats(),
            'quota' => app(QuotaService::class)->summary($company),
            'users' => $company->users()->with('roles')->orderBy('name')->get(),
            'canManageUsers' => $request->user()->hasFeature(AdminPermission::MANAGE_COMPANIES),
            'canAddUser' => $company->canAddUser(),
            'canManageLimits' => $request->user()->hasFeature(AdminPermission::MANAGE_SEAT_LIMITS),
            'canResetQuotas' => $request->user()->hasFeature(AdminPermission::RESET_QUOTAS),
            'canViewCrm' => $request->user()->hasFeature(AdminPermission::VIEW_CRM),
            'recentBookings' => $company->bookings()->with('trip')->latest()->limit(5)->get(),
            'recentPayments' => $company->payments()->latest()->limit(5)->get(),
            'recentDocuments' => $company->documents()->latest()->limit(5)->get(),
            'roleOptions' => [
                Role::COMPANY_ADMIN => 'Company Administrator',
                Role::DISPATCHER => 'Dispatcher',
                Role::DRIVER => 'Driver',
                Role::CLIENT => 'Client',
            ],
        ]);
    }

    /**
     * Update company limits, policy and legal details.
     */
    public function update(
        Request $request,
        Company $company,
        CompanySeatLimitService $seatLimits,
    ): RedirectResponse {
        $data = $request->validate([
            'legal_name' => ['nullable', 'string', 'max:160'],
            'tax_number' => ['nullable', 'string', 'max:60'],
            'address' => ['nullable', 'string', 'max:255'],
            'contact' => ['nullable', 'string', 'max:255'],
            'seat_limit' => ['required', 'integer', 'min:0', 'max:1000000'],
            'partial_payment_allowed' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $old = $company->only(['legal_name', 'tax_number', 'address', 'contact', 'partial_payment_allowed', 'is_active']);

        $company->update([
            'legal_name' => $data['legal_name'] ?? null,
            'tax_number' => $data['tax_number'] ?? null,
            'address' => $data['address'] ?? null,
            'contact' => $data['contact'] ?? null,
            'partial_payment_allowed' => (bool) ($data['partial_payment_allowed'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        $seatLimits->update($company, (int) $data['seat_limit'], $request->user());

        AuditLog::record($company, 'company.updated', $request->user(), $old, $company->only([
            'legal_name', 'tax_number', 'address', 'contact', 'partial_payment_allowed', 'is_active',
        ]));

        return redirect()
            ->route('admin.companies.edit', $company)
            ->with('status', 'Company details and seat limit updated.');
    }

    /**
     * Reset the usage window and clear unused bonus seats.
     */
    public function resetQuota(Request $request, Company $company, QuotaService $quotas): RedirectResponse
    {
        $quotas->reset($company, $request->user());

        return back()->with('status', 'Quota window reset. Unused bonus seats were cleared.');
    }

    public function storeContact(Request $request, Company $company): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'role' => ['nullable', 'string', 'max:80'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
        ]);

        $contact = $company->contacts()->create($data);
        AuditLog::record($contact, 'company.contact_added', $request->user(), null, $data);

        return back()->with('status', 'Contact added.');
    }

    public function destroyContact(Request $request, Company $company, int $contact): RedirectResponse
    {
        $record = $company->contacts()->findOrFail($contact);
        AuditLog::record($record, 'company.contact_removed', $request->user(), $record->only(['name', 'email', 'phone']));
        $record->delete();

        return back()->with('status', 'Contact removed.');
    }

    public function storeNote(Request $request, Company $company): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:4000'],
        ]);

        $note = $company->notes()->create([
            'body' => $data['body'],
            'author_id' => $request->user()->getKey(),
        ]);

        AuditLog::record($note, 'crm.note_added', $request->user(), null, ['body' => $data['body']]);

        return back()->with('status', 'CRM note saved.');
    }

    public function storeActivity(Request $request, Company $company): RedirectResponse
    {
        $data = $request->validate([
            'kind' => ['required', 'string', 'max:40'],
            'summary' => ['required', 'string', 'max:255'],
        ]);

        $activity = $company->activities()->create([
            'kind' => $data['kind'],
            'summary' => $data['summary'],
            'actor_id' => $request->user()->getKey(),
            'occurred_at' => now(),
        ]);

        AuditLog::record($activity, 'crm.activity_added', $request->user(), null, $data);

        return back()->with('status', 'Activity logged.');
    }
}
