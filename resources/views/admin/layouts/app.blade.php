<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') &middot; {{ config('app.name') }} Admin</title>
    @vite(['resources/css/admin/app.css', 'resources/js/admin/app.js'])
</head>
<body>
    <div class="app">
        <aside class="sidebar">
            <div class="brand">
                <span class="brand-mark">B2B</span>
                <span class="brand-name">Transport ERP</span>
            </div>

            <p class="portal-label">Admin Portal</p>

            <nav class="nav">
                <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'is-active' : '' }}" href="{{ route('admin.dashboard') }}">Dashboard</a>
                <a class="nav-link {{ request()->routeIs('admin.companies.*') ? 'is-active' : '' }}" href="{{ route('admin.companies.index') }}">Companies</a>
                <a class="nav-link {{ request()->routeIs('admin.trips.*') ? 'is-active' : '' }}" href="{{ route('admin.trips.index') }}">Trips</a>
                @if (auth()->user()?->hasFeature(\App\Models\AdminPermission::MANAGE_TEMPLATES))
                    <a class="nav-link {{ request()->routeIs('admin.templates.*') ? 'is-active' : '' }}" href="{{ route('admin.templates.index') }}">Templates</a>
                @endif
                <a class="nav-link {{ request()->routeIs('admin.seat-requests.*') ? 'is-active' : '' }}" href="{{ route('admin.seat-requests.index') }}">Seat Requests</a>
                @if (auth()->user()?->hasFeature(\App\Models\AdminPermission::RECORD_PAYMENTS) || auth()->user()?->hasFeature(\App\Models\AdminPermission::PREPARE_DOCUMENTS))
                    <a class="nav-link {{ request()->routeIs('admin.payments.*') ? 'is-active' : '' }}" href="{{ auth()->user()?->hasFeature(\App\Models\AdminPermission::RECORD_PAYMENTS) ? route('admin.payments.index') : route('admin.payments.documents.index') }}">Payments</a>
                @endif
                @if (auth()->user()?->hasFeature(\App\Models\AdminPermission::VIEW_REPORTS))
                    <a class="nav-link {{ request()->routeIs('admin.reports.index') ? 'is-active' : '' }}" href="{{ route('admin.reports.index') }}">Reports</a>
                @endif
                @if (auth()->user()?->hasFeature(\App\Models\AdminPermission::VIEW_FINANCE_REPORTS))
                    <a class="nav-link {{ request()->routeIs('admin.reports.finance*') ? 'is-active' : '' }}" href="{{ route('admin.reports.finance') }}">Finance</a>
                @endif
                @if (auth()->user()?->hasFeature(\App\Models\AdminPermission::VIEW_REPORTS))
                    <a class="nav-link {{ request()->routeIs('admin.audit-logs.*') ? 'is-active' : '' }}" href="{{ route('admin.audit-logs.index') }}">Audit Log</a>
                @endif
                @if (auth()->user()?->isSuperAdmin() || auth()->user()?->hasFeature(\App\Models\AdminPermission::MANAGE_ADMINS))
                    <a class="nav-link {{ request()->routeIs('admin.settings.*') ? 'is-active' : '' }}" href="{{ route('admin.settings.index') }}">Settings</a>
                @endif
            </nav>

            <div class="sidebar-footer">
                <span class="dot"></span>
                <span>{{ auth()->user()?->name ?? 'Administrator' }}</span>
            </div>
        </aside>

        <div class="main">
            <header class="topbar">
                <div>
                    <p class="eyebrow">@yield('eyebrow', 'Administration')</p>
                    <h1 class="page-title">@yield('page-title', 'Dashboard')</h1>
                </div>
                <div class="topbar-meta">
                    <span class="pill">{{ auth()->user()?->isSuperAdmin() ? 'Super Admin' : 'Admin' }}</span>
                    <form method="POST" action="{{ route('admin.logout') }}">
                        @csrf
                        <button class="btn btn-ghost btn-small" type="submit">Sign out</button>
                    </form>
                </div>
            </header>

            <main class="content">
                @if (session('status'))
                    <div class="flash" role="status">{{ session('status') }}</div>
                @endif

                @if ($errors->any())
                    <div class="alert alert-error">
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>
    @stack('scripts')
</body>
</html>
