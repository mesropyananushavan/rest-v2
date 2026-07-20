<?php

declare(strict_types=1);

/** @var string $id */
/** @var string $action */
/** @var string $title */
/** @var string $message */
/** @var string $confirmLabel */
/** @var string $cancelLabel */
/** @var string $triggerLabel */
/** @var string $method */
?>

@props([
    'id',
    'action',
    'title' => __('admin.components.confirm_delete.title'),
    'message' => __('admin.components.confirm_delete.message'),
    'confirmLabel' => __('admin.actions.delete'),
    'cancelLabel' => __('admin.actions.cancel'),
    'triggerLabel' => __('admin.actions.delete'),
    'method' => 'delete',
])

<button type="button" {{ $attributes->class(['btn btn-sm btn-outline-danger']) }} data-bs-toggle="modal" data-bs-target="#{{ $id }}">
    {{ $triggerLabel }}
</button>

<div class="modal fade" id="{{ $id }}" tabindex="-1" aria-labelledby="{{ $id }}_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="{{ $id }}_title">{{ $title }}</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ $cancelLabel }}"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">{{ $message }}</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    {{ $cancelLabel }}
                </button>
                <form method="post" action="{{ $action }}">
                    @csrf
                    @method($method)
                    <button type="submit" class="btn btn-danger">
                        {{ $confirmLabel }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
