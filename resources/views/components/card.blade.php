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

<section {{ $attributes->class(['sr-card card']) }}>
    @if ($title !== null || isset($actions))
        <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
            @if ($title !== null)
                <h2 class="h5 mb-0">{{ $title }}</h2>
            @endif
            <div class="d-flex align-items-center gap-2">
                @if ($count !== null)
                    <span class="badge text-bg-light">{{ $count }}</span>
                @endif
                @isset($actions)
                    {{ $actions }}
                @endisset
            </div>
        </div>
    @endif

    <div @class(['card-body', $bodyClass])>
        {{ $slot }}
    </div>
</section>
