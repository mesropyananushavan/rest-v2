<?php declare(strict_types=1); ?>
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>@yield('title', config('app.name'))</title>
        @if (file_exists(public_path('build/manifest.json')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body>
        <nav class="navbar navbar-expand-lg bg-white border-bottom">
            <div class="container-fluid">
                <a class="navbar-brand fw-semibold" href="{{ route('admin.menu.index') }}">{{ config('app.name') }}</a>
                <div class="d-flex align-items-center gap-3">
                    <span class="text-muted small">{{ auth()->user()?->name }}</span>
                    <form method="post" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn btn-outline-secondary btn-sm">
                            {{ __('auth.logout.submit') }}
                        </button>
                    </form>
                </div>
            </div>
        </nav>

        <main class="container-fluid py-4">
            @if (session('status'))
                <div class="alert alert-success" role="status">
                    {{ session('status') }}
                </div>
            @endif

            @yield('content')
        </main>
    </body>
</html>
