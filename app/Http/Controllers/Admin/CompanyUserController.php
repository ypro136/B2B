<?php

namespace App\Http\Controllers\Admin;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CompanyUserController extends BaseController
{
    /**
     * Create a login account for a company user.
     */
    public function store(Request $request, Company $company): RedirectResponse
    {
        if (! $company->canAddUser()) {
            throw ValidationException::withMessages([
                'users' => 'A company can have at most two user accounts.',
            ]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::in([
                Role::COMPANY_ADMIN,
                Role::DISPATCHER,
                Role::DRIVER,
                Role::CLIENT,
            ])],
        ]);

        try {
            $user = DB::transaction(function () use ($company, $data, $request) {
                $user = User::query()->create([
                    'company_id' => $company->getKey(),
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => $data['password'],
                    'is_active' => true,
                ]);

                $role = Role::query()->firstOrCreate(
                    ['name' => $data['role']],
                    ['display_name' => ucwords(strtolower(str_replace('_', ' ', $data['role'])))],
                );

                $user->roles()->attach($role);

                AuditLog::record($user, 'company_user.created', $request->user(), null, [
                    'company_id' => $company->getKey(),
                    'email' => $user->email,
                    'role' => $role->name,
                ]);

                return $user;
            });
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages([
                'users' => collect($exception->errors())->flatten()->first()
                    ?? 'The company user could not be created.',
            ]);
        }

        return redirect()
            ->route('admin.companies.edit', $company)
            ->with('status', 'User account created for '.$user->name.'.');
    }

    /**
     * Activate or deactivate a company user account.
     */
    public function toggle(Request $request, Company $company, User $user): RedirectResponse
    {
        abort_unless((int) $user->company_id === (int) $company->getKey(), 404);

        $wasActive = $user->is_active;
        $user->update(['is_active' => ! $wasActive]);

        AuditLog::record(
            $user,
            $user->is_active ? 'company_user.activated' : 'company_user.deactivated',
            $request->user(),
            ['is_active' => $wasActive],
            ['is_active' => $user->is_active],
        );

        return back()->with(
            'status',
            $user->name.' was '.($user->is_active ? 'activated' : 'deactivated').'.',
        );
    }
}
