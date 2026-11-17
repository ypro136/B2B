<?php

namespace App\Http\Controllers\Admin;

use App\Models\AdminPermission;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PermissionController extends BaseController
{
    /**
     * Grant a feature to a regular administrator.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'admin_user_id' => ['required', 'integer', 'exists:users,id'],
            'feature' => ['required', 'string', 'in:'.implode(',', AdminPermission::features())],
        ]);

        $admin = User::query()->findOrFail($data['admin_user_id']);

        if (! $admin->isAdmin()) {
            throw ValidationException::withMessages([
                'admin_user_id' => 'The selected user is not an administrator.',
            ]);
        }

        AdminPermission::query()->firstOrCreate(
            [
                'admin_user_id' => $admin->getKey(),
                'feature' => $data['feature'],
            ],
            [
                'granted_by' => $request->user()->getKey(),
                'granted_at' => now(),
            ],
        );

        AuditLog::record($admin, 'admin_permission.granted', $request->user(), null, [
            'feature' => $data['feature'],
        ]);

        return back()->with('status', 'Permission granted.');
    }

    /**
     * Revoke a feature from an administrator.
     */
    public function destroy(Request $request, User $user, string $feature): RedirectResponse
    {
        $deleted = $user->adminPermissions()->where('feature', $feature)->delete();

        if ($deleted > 0) {
            AuditLog::record($user, 'admin_permission.revoked', $request->user(), [
                'feature' => $feature,
            ]);
        }

        return back()->with('status', 'Permission revoked.');
    }
}
