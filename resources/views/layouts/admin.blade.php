<?php

declare(strict_types=1);

/** @var array{tenant_name: string|null, branch_name: string|null} $adminShell */

$tenantName = $adminShell['tenant_name'] ?? __('admin.shell.no_tenant');
$branchName = $adminShell['branch_name'] ?? __('admin.shell.no_branch');
?>
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>@yield('title', __('admin.brand.name'))</title>
        @if (file_exists(public_path('build/manifest.json')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="sr-admin-body">
        <div class="sr-admin-shell">
            <aside id="adminSidebar" class="sr-sidebar collapse d-lg-flex">
                <div class="sr-sidebar-brand">
                    <a href="{{ route('admin.dashboard') }}" class="sr-brand-mark" aria-label="{{ __('admin.brand.name') }}">
                        <span class="sr-brand-symbol">SR</span>
                        <span>
                            <span class="sr-brand-name">{{ __('admin.brand.name') }}</span>
                            <span class="sr-brand-tagline">{{ __('admin.brand.tagline') }}</span>
                        </span>
                    </a>
                </div>

                <nav class="sr-nav" aria-label="{{ __('admin.shell.toggle_navigation') }}">
                    <a href="{{ route('admin.dashboard') }}" class="sr-nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                        <span class="sr-nav-dot"></span>
                        <span>{{ __('admin.nav.dashboard') }}</span>
                    </a>
                    <a href="{{ route('admin.menu.index') }}" class="sr-nav-link {{ request()->routeIs('admin.menu.*') ? 'active' : '' }}">
                        <span class="sr-nav-dot"></span>
                        <span>{{ __('admin.nav.menu') }}</span>
                    </a>
                </nav>
            </aside>

            <div class="sr-admin-main">
                <header class="sr-topbar">
                    <button
                        class="btn btn-outline-secondary btn-sm d-lg-none"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#adminSidebar"
                        aria-controls="adminSidebar"
                        aria-expanded="false"
                        aria-label="{{ __('admin.shell.toggle_navigation') }}"
                    >
                        <span class="navbar-toggler-icon"></span>
                    </button>

                    <div class="sr-context-grid">
                        <div class="sr-context-pill">
                            <span>{{ __('admin.shell.tenant') }}</span>
                            <strong>{{ $tenantName }}</strong>
                        </div>
                        <div class="sr-context-pill">
                            <span>{{ __('admin.shell.branch') }}</span>
                            <strong>{{ $branchName }}</strong>
                        </div>
                    </div>

                    <div class="sr-user-menu">
                        <div class="text-end">
                            <span class="d-block text-muted small">{{ __('admin.shell.signed_in_as') }}</span>
                            <strong class="small">{{ auth()->user()?->name }}</strong>
                        </div>
                        <form method="post" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="btn btn-outline-secondary btn-sm">
                                {{ __('auth.logout.submit') }}
                            </button>
                        </form>
                    </div>
                </header>

                <main class="sr-content">
                    @if (session('status'))
                        <div class="alert alert-success" role="status">
                            {{ session('status') }}
                        </div>
                    @endif

                    @if (session('error'))
                        <div class="alert alert-danger" role="alert">
                            {{ session('error') }}
                        </div>
                    @endif

                    @yield('content')
                </main>
            </div>
        </div>
    </body>
</html>
