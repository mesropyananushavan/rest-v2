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
