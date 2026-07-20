<?php

declare(strict_types=1);
?>

@extends('layouts.admin')

@section('title', __('admin.errors.403.title'))

@section('content')
    <x-page-header
        :eyebrow="__('admin.errors.eyebrow')"
        :title="__('admin.errors.403.title')"
        :subtitle="__('admin.errors.403.message')"
    />

    <x-card>
        <x-button :href="route('admin.dashboard')" variant="outline-secondary">
            {{ __('admin.errors.actions.dashboard') }}
        </x-button>
    </x-card>
@endsection
