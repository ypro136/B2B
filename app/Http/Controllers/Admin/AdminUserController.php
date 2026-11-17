<?php

namespace App\Http\Controllers\Admin;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminUserController extends BaseController
{
    /**
     * Create a new administrator account from the Settings tab.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::in([Role::ADMIN, Role::SUPER_ADMIN])],
        ]);

        DB::transaction(function () use ($data, $request) {
            $user = User::query()->create([
                'company_id' => null,
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

            AuditLog::record($user, 'admin.created', $request->user(), null, [
                'email' => $user->email,
                'role' => $role->name,
            ]);
        });

        return back()->with('status', 'Administrator account created for '.$data['name'].'.');
    }
}
