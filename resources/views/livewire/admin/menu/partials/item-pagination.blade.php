<?php

declare(strict_types=1);

use App\Modules\Menu\Infrastructure\Models\MenuItem;
use Illuminate\Pagination\LengthAwarePaginator;

/** @var LengthAwarePaginator<int, MenuItem> $items */
/** @var string $nextMethod */
/** @var string $previousMethod */
?>

@if ($items->hasPages())
    <div class="flex items-center justify-between gap-2 border-t border-slate-100 p-4 text-sm text-smartrest-muted">
        <span>{{ __('menu.pagination.page_of', ['page' => $items->currentPage(), 'pages' => $items->lastPage()]) }}</span>
        <div class="flex gap-2">
            <x-button type="button" variant="outline-secondary" size="sm" wire:click="{{ $previousMethod }}" :disabled="$items->onFirstPage()">
                {{ __('menu.pagination.previous') }}
            </x-button>
            <x-button type="button" variant="outline-secondary" size="sm" wire:click="{{ $nextMethod }}" :disabled="! $items->hasMorePages()">
                {{ __('menu.pagination.next') }}
            </x-button>
        </div>
    </div>
@endif
