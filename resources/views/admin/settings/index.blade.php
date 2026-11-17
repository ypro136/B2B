@extends($layout)

@section('title', 'Settings')
@section('eyebrow', 'Administration')
@section('page-title', 'Settings')

@section('content')
    @include('admin::partials.settings-subnav')

    <div class="page-head">
        <div>
            <h2>Settings, permissions and admins</h2>
            <p class="muted">Platform configuration lives here. Settings edits and permission grants are restricted to super administrators; adding an admin requires the MANAGE_ADMINS permission.</p>
        </div>
    </div>

    @if ($isSuperAdmin)
        <section id="settings" class="form-card">
            <h2>Platform settings</h2>
            <p class="muted">Changes apply to future bookings and new documents.</p>
            <form method="POST" action="{{ route('admin.settings.update') }}">
                @csrf
                @method('PUT')

                <div class="form-grid">
                    <div class="field">
                        <label for="booking_cutoff_hours">Default booking cutoff (hours)</label>
                        <input id="booking_cutoff_hours" name="booking_cutoff_hours" type="number" min="0" max="168" value="{{ old('booking_cutoff_hours', $settings['booking_cutoff_hours']) }}" required>
                    </div>
                    <div class="field">
                        <label for="currency">Currency</label>
                        <input id="currency" name="currency" type="text" maxlength="3" value="{{ old('currency', $settings['currency']) }}" required>
                    </div>
                    <div class="field">
                        <label for="business_timezone">Business timezone</label>
                        <input id="business_timezone" name="business_timezone" type="text" maxlength="60" value="{{ old('business_timezone', $settings['business_timezone']) }}" required>
                    </div>
                    <div class="field">
                        <label for="vat_rate">VAT rate (%)</label>
                        <input id="vat_rate" name="vat_rate" type="number" step="0.01" min="0" max="100" value="{{ old('vat_rate', $settings['vat_rate']) }}" required>
                    </div>
                    <div class="field">
                        <label>VAT enabled</label>
                        <label class="checkbox">
                            <input type="hidden" name="vat_enabled" value="0">
                            <input type="checkbox" name="vat_enabled" value="1" @checked(old('vat_enabled', $settings['vat_enabled']) == '1')>
                            Apply VAT to new bookings
                        </label>
                    </div>
                    <div class="field">
                        <label for="issuer_name">Issuer name</label>
                        <input id="issuer_name" name="issuer_name" type="text" maxlength="160" value="{{ old('issuer_name', $settings['issuer_name']) }}" required>
                    </div>
                    <div class="field">
                        <label for="issuer_tax_number">Issuer tax number</label>
                        <input id="issuer_tax_number" name="issuer_tax_number" type="text" maxlength="60" value="{{ old('issuer_tax_number', $settings['issuer_tax_number']) }}">
                    </div>
                    <div class="field is-wide">
                        <label for="issuer_address">Issuer address</label>
                        <input id="issuer_address" name="issuer_address" type="text" maxlength="255" value="{{ old('issuer_address', $settings['issuer_address']) }}">
                    </div>
                    <div class="field">
                        <label for="invoice_prefix">Invoice prefix</label>
                        <input id="invoice_prefix" name="invoice_prefix" type="text" maxlength="10" value="{{ old('invoice_prefix', $settings['invoice_prefix']) }}" required>
                    </div>
                    <div class="field">
                        <label for="receipt_prefix">Receipt prefix</label>
                        <input id="receipt_prefix" name="receipt_prefix" type="text" maxlength="10" value="{{ old('receipt_prefix', $settings['receipt_prefix']) }}" required>
                    </div>
                </div>

                <div class="form-actions">
                    <button class="btn btn-primary" type="submit">Save settings</button>
                </div>
            </form>
        </section>

        <section id="permissions" style="margin-top: 32px;">
            <div class="page-head">
                <div>
                    <h2>Admin permissions</h2>
                    <p class="muted">Only a super administrator can grant or revoke admin features.</p>
                </div>
            </div>

            @php($grantableAdmins = $admins->reject(fn ($admin) => $admin->isSuperAdmin()))
            <div class="form-card">
                <form method="POST" action="{{ route('admin.settings.permissions.store') }}" class="inline-form">
                    @csrf
                    <div class="field">
                        <label for="admin_user_id">Administrator</label>
                        <select id="admin_user_id" name="admin_user_id" required>
                            @foreach ($grantableAdmins as $admin)
                                <option value="{{ $admin->id }}">{{ $admin->name }} ({{ $admin->email }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label for="feature">Feature</label>
                        <select id="feature" name="feature" required>
                            @foreach ($features as $feature)
                                <option value="{{ $feature }}">{{ $feature }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button class="btn btn-primary" type="submit">Grant permission</button>
                </form>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Administrator</th>
                            <th>Role</th>
                            <th>Granted features</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($admins as $admin)
                            <tr>
                                <td>
                                    <span class="strong">{{ $admin->name }}</span><br>
                                    <span class="muted">{{ $admin->email }}</span>
                                </td>
                                <td>
                                    @foreach ($admin->roles as $role)
                                        <span class="badge badge-muted">{{ $role->name }}</span>
                                    @endforeach
                                </td>
                                <td>
                                    @if ($admin->isSuperAdmin())
                                        <span class="badge badge-success">All features (super admin)</span>
                                    @elseif ($admin->adminPermissions->isEmpty())
                                        <span class="muted">No features granted.</span>
                                    @else
                                        <div class="actions">
                                            @foreach ($admin->adminPermissions as $permission)
                                                <form method="POST" action="{{ route('admin.settings.permissions.destroy', [$admin, $permission->feature]) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="btn btn-small btn-ghost" type="submit" title="Revoke {{ $permission->feature }}">
                                                        {{ $permission->feature }} &times;
                                                    </button>
                                                </form>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3"><div class="empty">No administrators found.</div></td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    @if ($canManageAdmins)
        <section id="add-admin" class="form-card" style="margin-top: 32px;">
            <h2>Add administrator</h2>
            <p class="muted">Creates an admin account (no company). ADMIN accounts start with no feature permissions; SUPER_ADMIN accounts have all features. New accounts are recorded in the audit log.</p>
            <form method="POST" action="{{ route('admin.settings.admins.store') }}">
                @csrf

                <div class="form-grid">
                    <div class="field">
                        <label for="name">Name</label>
                        <input id="name" name="name" type="text" maxlength="120" value="{{ old('name') }}" required>
                    </div>
                    <div class="field">
                        <label for="email">Email</label>
                        <input id="email" name="email" type="email" maxlength="255" value="{{ old('email') }}" required>
                    </div>
                    <div class="field">
                        <label for="role">Role</label>
                        <select id="role" name="role" required>
                            <option value="ADMIN" @selected(old('role') === 'ADMIN')>Admin</option>
                            <option value="SUPER_ADMIN" @selected(old('role') === 'SUPER_ADMIN')>Super Admin</option>
                        </select>
                    </div>
                    <div class="field">
                        <label for="password">Password</label>
                        <input id="password" name="password" type="password" minlength="8" required>
                    </div>
                    <div class="field">
                        <label for="password_confirmation">Confirm password</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" minlength="8" required>
                    </div>
                </div>

                <div class="form-actions">
                    <button class="btn btn-primary" type="submit">Create admin</button>
                </div>
            </form>
        </section>
    @endif
@endsection
