<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;

it('renders the reusable admin Blade components', function (): void {
    View::share('errors', new ViewErrorBag);

    $html = Blade::render(<<<'BLADE'
        <x-page-header title="Kitchen" eyebrow="Admin" subtitle="Ready">
            <x-slot:actions>
                <x-button href="/admin" variant="primary">Open</x-button>
            </x-slot:actions>
        </x-page-header>
        <x-card title="Summary" count="2">Card body</x-card>
        <x-table><tbody><tr><td>Row</td></tr></tbody></x-table>
        <x-badge-status :active="true" active-label="Active" inactive-label="Inactive" />
        <x-confirm-modal id="delete_test" action="/delete" />
        <x-form.input name="name" label="Name" value="Lavash" />
        <x-form.select name="category_id" label="Category" :options="[1 => 'Breakfast']" :selected="1" />
        <x-form.toggle name="active" label="Active" :checked="true" />
    BLADE);

    expect($html)
        ->toContain('rounded-sr-panel')
        ->toContain('rounded-sr-card')
        ->toContain('min-w-full')
        ->toContain('bg-smartrest-success/10')
        ->toContain('x-data')
        ->toContain('delete_test')
        ->toContain('Lavash')
        ->toContain('Breakfast')
        ->toContain('checked');
});

it('renders the confirm modal Livewire mode without changing the default HTTP mode', function (): void {
    View::share('errors', new ViewErrorBag);

    $httpHtml = Blade::render(<<<'BLADE'
        <x-confirm-modal id="delete_test" action="/delete" />
    BLADE);

    $livewireHtml = Blade::render(<<<'BLADE'
        <x-confirm-modal
            id="remove_line_test"
            livewire-method="confirmRemoveItem"
            :livewire-arguments="[123]"
            trigger-label="Remove"
            confirm-label="Remove"
        />
    BLADE);

    expect($httpHtml)
        ->toContain('delete_test')
        ->toContain('<form method="post" action="/delete">')
        ->toContain('name="_method"')
        ->toContain('value="delete"')
        ->and($livewireHtml)
        ->toContain('remove_line_test')
        ->toContain('wire:click="confirmRemoveItem(123)"')
        ->not->toContain('<form')
        ->not->toContain('wire:submit');
});

it('does not treat ordinary at signs in rendered attributes as uncompiled Blade directives', function (): void {
    $html = Blade::render(<<<'BLADE'
        <a href="mailto:info@example.com" title="a@b.com">Email</a>
    BLADE);

    assertRenderedHtmlHasNoUncompiledBladeDirectiveAttributes($html);

    expect($html)
        ->toContain('mailto:info@example.com')
        ->toContain('title="a@b.com"');
});
