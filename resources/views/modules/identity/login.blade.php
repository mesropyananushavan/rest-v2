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
    <body class="min-vh-100 d-flex align-items-center py-5">
        <main class="container">
            <div class="row justify-content-center">
                <div class="col-12 col-sm-10 col-md-7 col-lg-5">
                    <section class="sr-card card border-0">
                        <div class="card-body p-4 p-md-5">
                            <p class="text-uppercase text-muted small mb-2">{{ __('admin.brand.name') }}</p>
                            <h1 class="h3 mb-2">{{ __('auth.login.heading') }}</h1>
                            <p class="text-muted mb-4">{{ __('auth.login.subtitle') }}</p>

                            @if ($errors->any())
                                <div class="alert alert-danger" role="alert">
                                    {{ $errors->first() }}
                                </div>
                            @endif

                            <form method="post" action="{{ route('login.store') }}" novalidate>
                                @csrf

                                <div class="mb-3">
                                    <label for="email" class="form-label">{{ __('auth.fields.email') }}</label>
                                    <input
                                        id="email"
                                        name="email"
                                        type="email"
                                        value="{{ old('email') }}"
                                        class="form-control @error('email') is-invalid @enderror"
                                        autocomplete="email"
                                        autofocus
                                        required
                                    >
                                </div>

                                <div class="mb-4">
                                    <label for="password" class="form-label">{{ __('auth.fields.password') }}</label>
                                    <input
                                        id="password"
                                        name="password"
                                        type="password"
                                        class="form-control @error('password') is-invalid @enderror"
                                        autocomplete="current-password"
                                        required
                                    >
                                </div>

                                <button type="submit" class="btn btn-primary w-100">
                                    {{ __('auth.login.submit') }}
                                </button>
                            </form>
                        </div>
                    </section>
                </div>
            </div>
        </main>
    </body>
</html>
