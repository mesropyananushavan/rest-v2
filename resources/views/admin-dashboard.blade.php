<?php

declare(strict_types=1);
?>

@extends('layouts.admin')

@section('title', __('admin.dashboard.title'))

@section('content')
    <x-page-header
        :eyebrow="__('admin.dashboard.eyebrow')"
        :title="__('admin.dashboard.heading', ['name' => auth()->user()?->name])"
        :subtitle="__('admin.dashboard.subtitle')"
    />

    <livewire:admin.dashboard-counters />
@endsection
