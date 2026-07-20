<?php

declare(strict_types=1);

/** @var array{tenant_name: string|null, branch_name: string|null, branch_id: int|null, branch_options: list<array{id: int, name: string}>, locale: string} $adminShell */

$tenantName = $adminShell['tenant_name'] ?? __('admin.shell.no_tenant');
$branchName = $adminShell['branch_name'] ?? __('admin.shell.no_branch');
$branchId = $adminShell['branch_id'] ?? null;
$branchOptions = $adminShell['branch_options'] ?? [];
$locale = $adminShell['locale'] ?? app()->getLocale();
?>
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>@yield('title', __('admin.brand.name'))</title>
        @livewireStyles
        @if (file_exists(public_path('build/manifest.json')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="min-h-screen bg-smartrest-bg text-smartrest-text antialiased">
        <div x-data="{ sidebarOpen: false }" class="min-h-screen lg:flex">
            <button
                type="button"
                class="fixed inset-0 z-30 bg-slate-950/40 transition lg:hidden"
                x-cloak
                x-show="sidebarOpen"
                x-transition.opacity
                @click="sidebarOpen = false"
                aria-label="{{ __('admin.shell.toggle_navigation') }}"
            ></button>

            <aside
                id="adminSidebar"
                class="fixed inset-y-0 left-0 z-40 flex w-sr-sidebar -translate-x-full flex-col gap-6 bg-smartrest-sidebar p-4 text-white shadow-sr-sidebar transition-transform duration-200 lg:static lg:translate-x-0"
                :class="{ 'translate-x-0': sidebarOpen, '-translate-x-full': ! sidebarOpen }"
            >
                <div class="border-b border-white/10 pb-4">
                    <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 text-white no-underline" aria-label="{{ __('admin.brand.name') }}">
                        <span class="inline-flex h-11 w-11 items-center justify-center rounded-sr-brand bg-gradient-to-br from-smartrest-success to-green-800 font-extrabold tracking-wide">SR</span>
                        <span>
                            <span class="block font-extrabold tracking-tight">{{ __('admin.brand.name') }}</span>
                            <span class="block text-xs font-medium text-white/55">{{ __('admin.brand.tagline') }}</span>
                        </span>
                    </a>
                </div>

                <nav class="grid gap-1" aria-label="{{ __('admin.shell.toggle_navigation') }}">
                    <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 rounded-sr-brand px-3 py-3 text-sm font-semibold no-underline transition {{ request()->routeIs('admin.dashboard') ? 'bg-white/10 text-white' : 'text-white/70 hover:bg-white/10 hover:text-white' }}">
                        <span class="h-2 w-2 rounded-full {{ request()->routeIs('admin.dashboard') ? 'bg-smartrest-success' : 'bg-current opacity-50' }}"></span>
                        <span>{{ __('admin.nav.dashboard') }}</span>
                    </a>
                    <a href="{{ route('admin.menu.index') }}" class="flex items-center gap-3 rounded-sr-brand px-3 py-3 text-sm font-semibold no-underline transition {{ request()->routeIs('admin.menu.*') ? 'bg-white/10 text-white' : 'text-white/70 hover:bg-white/10 hover:text-white' }}">
                        <span class="h-2 w-2 rounded-full {{ request()->routeIs('admin.menu.*') ? 'bg-smartrest-success' : 'bg-current opacity-50' }}"></span>
                        <span>{{ __('admin.nav.menu') }}</span>
                    </a>
                </nav>
            </aside>

            <div class="min-w-0 flex-1">
                <header class="sticky top-0 z-20 flex min-h-sr-header flex-wrap items-start justify-between gap-3 border-b border-black/5 bg-white/85 px-4 py-3 backdrop-blur-sr lg:items-center lg:px-5">
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-sr-brand border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm lg:hidden"
                        @click="sidebarOpen = true"
                        aria-controls="adminSidebar"
                        :aria-expanded="sidebarOpen.toString()"
                        aria-label="{{ __('admin.shell.toggle_navigation') }}"
                    >
                        <span class="h-0.5 w-5 bg-current shadow-[0_6px_0_currentColor,0_-6px_0_currentColor]"></span>
                    </button>

                    <div class="order-3 grid w-full gap-3 sm:grid-cols-2 lg:order-none lg:w-auto lg:flex lg:items-center">
                        <div class="min-w-44 rounded-sr-brand border border-black/5 bg-white px-3 py-2 shadow-sm">
                            <span class="block text-[0.68rem] font-bold uppercase tracking-[0.16em] text-smartrest-muted">{{ __('admin.shell.tenant') }}</span>
                            <strong class="block truncate text-sm text-smartrest-ink">{{ $tenantName }}</strong>
                        </div>
                        <div class="min-w-44 rounded-sr-brand border border-black/5 bg-white px-3 py-2 shadow-sm">
                            <span class="block text-[0.68rem] font-bold uppercase tracking-[0.16em] text-smartrest-muted">{{ __('admin.shell.branch') }}</span>
                            @if ($branchOptions === [])
                                <strong class="block truncate text-sm text-smartrest-ink">{{ $branchName }}</strong>
                            @else
                                <form method="post" action="{{ route('admin.branch.switch') }}">
                                    @csrf
                                    <label class="sr-only" for="admin_branch_id">{{ __('admin.shell.switch_branch') }}</label>
                                    <select
                                        id="admin_branch_id"
                                        name="branch_id"
                                        class="w-full border-0 bg-transparent p-0 text-sm font-bold text-smartrest-ink outline-none"
                                        onchange="this.form.submit()"
                                    >
                                        @foreach ($branchOptions as $branch)
                                            <option value="{{ $branch['id'] }}" @selected($branchId === $branch['id'])>
                                                {{ $branch['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </form>
                            @endif
                        </div>
                        <div class="min-w-32 rounded-sr-brand border border-black/5 bg-white px-3 py-2 shadow-sm">
                            <span class="block text-[0.68rem] font-bold uppercase tracking-[0.16em] text-smartrest-muted">{{ __('admin.shell.locale') }}</span>
                            <form method="post" action="{{ route('admin.locale.switch') }}">
                                @csrf
                                <label class="sr-only" for="admin_locale">{{ __('admin.shell.switch_locale') }}</label>
                                <select
                                    id="admin_locale"
                                    name="locale"
                                    class="w-full border-0 bg-transparent p-0 text-sm font-bold text-smartrest-ink outline-none"
                                    onchange="this.form.submit()"
                                >
                                    @foreach (['hy', 'ru', 'en'] as $availableLocale)
                                        <option value="{{ $availableLocale }}" @selected($locale === $availableLocale)>
                                            {{ __('admin.locales.'.$availableLocale) }}
                                        </option>
                                    @endforeach
                                </select>
                            </form>
                        </div>
                    </div>

                    <div class="ml-auto flex items-center gap-3">
                        @auth
                            <div class="text-right">
                                <span class="block text-xs text-smartrest-muted">{{ __('admin.shell.signed_in_as') }}</span>
                                <strong class="block text-sm text-smartrest-ink">{{ auth()->user()?->name }}</strong>
                            </div>
                            <form method="post" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="inline-flex items-center justify-center rounded-sr-brand border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">
                                    {{ __('auth.logout.submit') }}
                                </button>
                            </form>
                        @else
                            <x-button :href="route('login')" variant="outline-secondary" size="sm">
                                {{ __('auth.login.submit') }}
                            </x-button>
                        @endauth
                    </div>
                </header>

                <main class="p-4 md:p-6">
                    <x-flash />

                    @yield('content')
                </main>
            </div>
        </div>
        @livewireScriptConfig
    </body>
</html>
