<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in &middot; {{ config('app.name') }} Admin</title>
    @vite(['resources/css/admin/app.css', 'resources/js/admin/app.js'])
</head>
<body>
    <div class="auth-shell">
        <div class="auth-card">
            <div class="auth-brand">
                <span class="brand-mark">B2B</span>
                <div>
                    <p class="portal-label">Admin Portal</p>
                    <strong>Transport ERP</strong>
                </div>
            </div>

            <div>
                <h1 class="auth-title">Sign in to administer</h1>
                <p class="auth-subtitle">Manage companies, trips, seat limits, and seat requests.</p>
            </div>

            @if ($errors->any())
                <div class="alert alert-error">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('admin.login.store') }}">
                @csrf
                <div class="form-grid" style="grid-template-columns: 1fr;">
                    <div class="field">
                        <label for="email">Email</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username">
                    </div>
                    <div class="field">
                        <label for="password">Password</label>
                        <input id="password" name="password" type="password" required autocomplete="current-password">
                    </div>
                </div>

                <label class="checkbox" style="margin-top: 12px;">
                    <input type="checkbox" name="remember" value="1">
                    Remember me
                </label>

                <button class="btn btn-primary" type="submit" style="width: 100%; margin-top: 16px;">Sign in</button>
            </form>

            <p class="muted" style="margin: 0; font-size: 12px;">Demo: superadmin@example.com / password</p>
        </div>
    </div>
</body>
</html>
