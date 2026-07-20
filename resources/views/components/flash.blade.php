<?php

declare(strict_types=1);
?>

@if (session('status'))
    <div {{ $attributes->class(['alert alert-success']) }} role="status">
        {{ session('status') }}
    </div>
@endif

@if (session('error'))
    <div {{ $attributes->class(['alert alert-danger']) }} role="alert">
        {{ session('error') }}
    </div>
@endif
