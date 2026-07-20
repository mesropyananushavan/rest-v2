<?php

declare(strict_types=1);

/** @var int $categoryCount */
/** @var int $itemCount */
?>

@extends('layouts.admin')

@section('title', __('admin.dashboard.title'))

@section('content')
    <div class="sr-page-hero mb-4">
        <div>
            <p class="text-uppercase text-muted small mb-2">{{ __('admin.dashboard.eyebrow') }}</p>
            <h1 class="display-6 fw-semibold mb-2">{{ __('admin.dashboard.heading', ['name' => auth()->user()?->name]) }}</h1>
            <p class="text-muted mb-0">{{ __('admin.dashboard.subtitle') }}</p>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-12 col-md-6 col-xl-4">
            <section class="sr-card card h-100">
                <div class="card-body p-4">
                    <p class="text-uppercase text-muted small mb-2">{{ __('admin.dashboard.metrics.categories.label') }}</p>
                    <div class="d-flex align-items-end gap-2">
                        <span class="sr-metric-value">{{ $categoryCount }}</span>
                        <span class="text-muted mb-2">{{ __('admin.dashboard.metrics.categories.unit') }}</span>
                    </div>
                    <p class="text-muted mb-0">{{ __('admin.dashboard.metrics.categories.help') }}</p>
                </div>
            </section>
        </div>

        <div class="col-12 col-md-6 col-xl-4">
            <section class="sr-card card h-100">
                <div class="card-body p-4">
                    <p class="text-uppercase text-muted small mb-2">{{ __('admin.dashboard.metrics.items.label') }}</p>
                    <div class="d-flex align-items-end gap-2">
                        <span class="sr-metric-value">{{ $itemCount }}</span>
                        <span class="text-muted mb-2">{{ __('admin.dashboard.metrics.items.unit') }}</span>
                    </div>
                    <p class="text-muted mb-0">{{ __('admin.dashboard.metrics.items.help') }}</p>
                </div>
            </section>
        </div>
    </div>
@endsection
