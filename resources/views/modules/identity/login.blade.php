<?php declare(strict_types=1); ?>
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ __('auth.login.title') }}</title>
        @if (file_exists(public_path('build/manifest.json')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="min-h-screen bg-smartrest-bg text-smartrest-text antialiased">
        <main class="flex min-h-screen items-center justify-center px-4 py-10">
            <section class="w-full max-w-md overflow-hidden rounded-2xl border border-black/5 bg-white shadow-sr-card">
                <div class="p-6 md:p-8">
                    <p class="mb-2 text-xs font-bold uppercase tracking-[0.18em] text-smartrest-muted">{{ __('admin.brand.name') }}</p>
                    <h1 class="mb-2 text-3xl font-semibold tracking-tight text-smartrest-ink">{{ __('auth.login.heading') }}</h1>
                    <p class="mb-6 text-sm leading-6 text-smartrest-muted">{{ __('auth.login.subtitle') }}</p>

                    @if ($errors->any())
                        <div class="mb-4 rounded-sr-brand border border-smartrest-danger/20 bg-red-50 px-4 py-3 text-sm font-medium text-red-800" role="alert">
                            {{ $errors->first() }}
                        </div>
                    @endif

                    <form method="post" action="{{ route('login.store') }}" novalidate>
                        @csrf

                        <div class="mb-4">
                            <label for="email" class="mb-1.5 block text-sm font-semibold text-slate-700">{{ __('auth.fields.email') }}</label>
                            <input
                                id="email"
                                name="email"
                                type="email"
                                value="{{ old('email') }}"
                                class="block w-full rounded-sr-control border bg-white px-3 py-2 text-sm text-smartrest-text shadow-sm outline-none transition focus:border-smartrest-success focus:ring-4 focus:ring-smartrest-success/10 @error('email') border-smartrest-danger @else border-slate-200 @enderror"
                                autocomplete="email"
                                autofocus
                                required
                            >
                        </div>

                        <div class="mb-6">
                            <label for="password" class="mb-1.5 block text-sm font-semibold text-slate-700">{{ __('auth.fields.password') }}</label>
                            <input
                                id="password"
                                name="password"
                                type="password"
                                class="block w-full rounded-sr-control border bg-white px-3 py-2 text-sm text-smartrest-text shadow-sm outline-none transition focus:border-smartrest-success focus:ring-4 focus:ring-smartrest-success/10 @error('password') border-smartrest-danger @else border-slate-200 @enderror"
                                autocomplete="current-password"
                                required
                            >
                        </div>

                        <button type="submit" class="inline-flex w-full items-center justify-center rounded-sr-brand bg-smartrest-success px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-green-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-smartrest-success">
                            {{ __('auth.login.submit') }}
                        </button>
                    </form>
                </div>
            </section>
        </main>
    </body>
</html>
