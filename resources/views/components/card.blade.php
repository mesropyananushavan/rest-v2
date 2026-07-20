<?php

declare(strict_types=1);

/** @var string|null $title */
/** @var int|string|null $count */
/** @var string $bodyClass */
?>

@props([
    'title' => null,
    'count' => null,
    'bodyClass' => 'p-4',
])

<section {{ $attributes->class(['overflow-hidden rounded-sr-card border border-black/5 bg-white shadow-sr-card']) }}>
    @if ($title !== null || isset($actions))
        <div class="flex items-center justify-between gap-3 border-b border-slate-100 bg-white px-4 py-3">
            @if ($title !== null)
                <h2 class="text-base font-semibold text-smartrest-ink">{{ $title }}</h2>
            @endif
            <div class="flex items-center gap-2">
                @if ($count !== null)
                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">{{ $count }}</span>
                @endif
                @isset($actions)
                    {{ $actions }}
                @endisset
            </div>
        </div>
    @endif

    <div @class([$bodyClass])>
        {{ $slot }}
    </div>
</section>
