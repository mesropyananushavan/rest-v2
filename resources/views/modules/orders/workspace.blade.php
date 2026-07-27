@extends('layouts.admin')

@section('title', __('orders.workspace.title', ['id' => $orderId]))

@section('content')
    <livewire:admin.order-workspace :order-id="$orderId" />
@endsection
