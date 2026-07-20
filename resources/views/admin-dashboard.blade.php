<?php

declare(strict_types=1);

/** @var int $categoryCount */
/** @var int $itemCount */
?>

@extends('layouts.admin')

@section('title', __('admin.dashboard.title'))

@section('content')
    <x-page-header
        :eyebrow="__('admin.dashboard.eyebrow')"
        :title="__('admin.dashboard.heading', ['name' => auth()->user()?->name])"
        :subtitle="__('admin.dashboard.subtitle')"
    />

    <div class="row g-4">
        <div class="col-12 col-md-6 col-xl-4">
            <x-card class="h-100">
                <p class="text-uppercase text-muted small mb-2">{{ __('admin.dashboard.metrics.categories.label') }}</p>
                <div class="d-flex align-items-end gap-2">
                    <span class="sr-metric-value">{{ $categoryCount }}</span>
                    <span class="text-muted mb-2">{{ __('admin.dashboard.metrics.categories.unit') }}</span>
                </div>
                <p class="text-muted mb-0">{{ __('admin.dashboard.metrics.categories.help') }}</p>
            </x-card>
        </div>

        <div class="col-12 col-md-6 col-xl-4">
            <x-card class="h-100">
                <p class="text-uppercase text-muted small mb-2">{{ __('admin.dashboard.metrics.items.label') }}</p>
                <div class="d-flex align-items-end gap-2">
                    <span class="sr-metric-value">{{ $itemCount }}</span>
                    <span class="text-muted mb-2">{{ __('admin.dashboard.metrics.items.unit') }}</span>
                </div>
                <p class="text-muted mb-0">{{ __('admin.dashboard.metrics.items.help') }}</p>
            </x-card>
        </div>
    </div>
@endsection
