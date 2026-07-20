<?php

declare(strict_types=1);
?>

@if (session('status'))
    <div {{ $attributes->class(['mb-4 rounded-sr-brand border border-smartrest-success/20 bg-smartrest-success/10 px-4 py-3 text-sm font-medium text-green-800']) }} role="status">
        {{ session('status') }}
    </div>
@endif

@if (session('error'))
    <div {{ $attributes->class(['mb-4 rounded-sr-brand border border-smartrest-danger/20 bg-red-50 px-4 py-3 text-sm font-medium text-red-800']) }} role="alert">
        {{ session('error') }}
    </div>
@endif
