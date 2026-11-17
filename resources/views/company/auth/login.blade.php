<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in &middot; {{ config('app.name') }} Company</title>
    @vite(['resources/css/company/app.css', 'resources/js/company/app.js'])
</head>
<body>
    <div class="auth-shell">
        <div class="auth-card">
            <div class="auth-brand">
                <span class="brand-mark">B2B</span>
                <div>
                    <p class="portal-label">Company Portal</p>
                    <strong>Transport ERP</strong>
                </div>
            </div>

            <div>
                <h1 class="auth-title">Sign in to book seats</h1>
                <p class="auth-subtitle">Reserve seats on scheduled trips and request additional capacity.</p>
            </div>

            @if ($errors->any())
                <div class="alert alert-error">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('company.login.store') }}">
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

            <p class="muted" style="margin: 0; font-size: 12px;">Demo: manager@acme.example / password</p>
        </div>
    </div>
</body>
</html>
