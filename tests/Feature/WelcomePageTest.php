<?php

declare(strict_types=1);

it('renders the translated welcome placeholder for supported locales', function (): void {
    foreach (['en', 'hy', 'ru'] as $locale) {
        app()->setLocale($locale);

        $this->get('/')
            ->assertOk()
            ->assertSee(__('welcome.heading'), false)
            ->assertSee(__('welcome.body'), false);
    }
});
