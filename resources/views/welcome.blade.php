<?php declare(strict_types=1); ?>
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ __('welcome.title') }}</title>
    </head>
    <body>
        <main>
            <p>{{ config('app.name') }}</p>
            <h1>{{ __('welcome.heading') }}</h1>
            <p>{{ __('welcome.body') }}</p>
        </main>
    </body>
</html>
