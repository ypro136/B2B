<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') &middot; {{ config('app.name') }} Company</title>
    @vite(['resources/css/company/app.css', 'resources/js/company/app.js'])
</head>
<body>
    <div class="app">
        <aside class="sidebar">
            <div class="brand">
                <span class="brand-mark">B2B</span>
                <span class="brand-name">Transport ERP</span>
            </div>

            <p class="portal-label">Company Portal</p>

            <nav class="nav">
                <a class="nav-link {{ request()->routeIs('company.dashboard') ? 'is-active' : '' }}" href="{{ route('company.dashboard') }}">Dashboard</a>
                <a class="nav-link {{ request()->routeIs('company.trips.*') ? 'is-active' : '' }}" href="{{ route('company.trips.index') }}">Trips</a>
                <a class="nav-link {{ request()->routeIs('company.bookings.*') ? 'is-active' : '' }}" href="{{ route('company.bookings.index') }}">Bookings</a>
                <a class="nav-link {{ request()->routeIs('company.seat-requests.*') ? 'is-active' : '' }}" href="{{ route('company.seat-requests.index') }}">Seat Requests</a>
                <a class="nav-link {{ request()->routeIs('company.documents.*') ? 'is-active' : '' }}" href="{{ route('company.documents.index') }}">Documents</a>
                <a class="nav-link {{ request()->routeIs('company.payments.*') ? 'is-active' : '' }}" href="{{ route('company.payments.index') }}">Payments</a>
                <a class="nav-link {{ request()->routeIs('company.credits.*') ? 'is-active' : '' }}" href="{{ route('company.credits.index') }}">Credits</a>
            </nav>

            <div class="sidebar-footer">
                <span class="dot"></span>
                <span>{{ auth()->user()?->name ?? 'Company User' }}</span>
            </div>
        </aside>

        <div class="main">
            <header class="topbar">
                <div>
                    <p class="eyebrow">@yield('eyebrow', 'Company Portal')</p>
                    <h1 class="page-title">@yield('page-title', 'Dashboard')</h1>
                </div>
                <div class="topbar-meta">
                    <span class="pill">{{ auth()->user()?->company?->name ?? 'Company Account' }}</span>
                    <form method="POST" action="{{ route('company.logout') }}">
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
