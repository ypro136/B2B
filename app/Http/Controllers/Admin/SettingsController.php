<?php

namespace App\Http\Controllers\Admin;

use App\Models\AdminPermission;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SettingsController extends BaseController
{
    public const KEYS = [
        'booking_cutoff_hours' => 'integer',
        'vat_enabled' => 'boolean',
        'vat_rate' => 'decimal',
        'currency' => 'string',
        'business_timezone' => 'string',
        'issuer_name' => 'string',
        'issuer_tax_number' => 'string',
        'issuer_address' => 'string',
        'invoice_prefix' => 'string',
        'receipt_prefix' => 'string',
    ];

    public function index(Request $request): View
    {
        $settings = [];

        foreach (self::KEYS as $key => $type) {
            $settings[$key] = Setting::get($key, $this->defaults()[$key] ?? null);
        }

        $user = $request->user();
        $isSuperAdmin = (bool) $user?->isSuperAdmin();

        return view('admin::settings.index', [
            'settings' => $settings,
            'isSuperAdmin' => $isSuperAdmin,
            'canManageAdmins' => (bool) $user?->hasFeature(AdminPermission::MANAGE_ADMINS),
            'admins' => $isSuperAdmin
                ? User::query()
                    ->whereHas('roles', fn ($query) => $query->whereIn('name', [Role::SUPER_ADMIN, Role::ADMIN]))
                    ->with(['roles', 'adminPermissions'])
                    ->orderBy('name')
                    ->get()
                : collect(),
            'features' => AdminPermission::features(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'booking_cutoff_hours' => ['required', 'integer', 'min:0', 'max:168'],
            'vat_enabled' => ['nullable', 'boolean'],
            'vat_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'currency' => ['required', 'string', 'size:3'],
            'business_timezone' => ['required', 'string', 'max:60'],
            'issuer_name' => ['required', 'string', 'max:160'],
            'issuer_tax_number' => ['nullable', 'string', 'max:60'],
            'issuer_address' => ['nullable', 'string', 'max:255'],
            'invoice_prefix' => ['required', 'string', 'max:10'],
            'receipt_prefix' => ['required', 'string', 'max:10'],
        ]);

        foreach (self::KEYS as $key => $type) {
            $value = $data[$key] ?? null;

            if ($type === 'boolean') {
                $value = $request->boolean($key) ? '1' : '0';
            }

            Setting::set($key, $value === null ? null : (string) $value, $type);
        }

        return back()->with('status', 'Settings saved. Future transactions will use the new values.');
    }

    /**
     * @return array<string, string>
     */
    private function defaults(): array
    {
        return [
            'booking_cutoff_hours' => '2',
            'vat_enabled' => '1',
            'vat_rate' => '14',
            'currency' => 'EGP',
            'business_timezone' => 'Africa/Cairo',
            'issuer_name' => 'Transportation Sys',
            'issuer_tax_number' => '',
            'issuer_address' => '',
            'invoice_prefix' => 'INV',
            'receipt_prefix' => 'RCT',
        ];
    }
}
