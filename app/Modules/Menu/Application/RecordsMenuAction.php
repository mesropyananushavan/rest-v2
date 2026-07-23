<?php

declare(strict_types=1);

namespace App\Modules\Menu\Application;

use App\Modules\Menu\Domain\MenuDomainException;
use App\Modules\Menu\Infrastructure\Models\MenuCategory;
use App\Modules\Menu\Infrastructure\Models\MenuItem;
use App\Support\Audit\AuditRecorder;
use App\Support\Logging\LogContext;
use App\Support\Logging\Redactor;
use DateTimeInterface;
use Illuminate\Support\Facades\Log;

trait RecordsMenuAction
{
    /**
     * @param  array<string, mixed>  $context
     */
    private function logSuccess(string $action, float $startedAt, array $context = []): void
    {
        LogContext::refreshRuntimeContext('menu');

        Log::info('action performed', Redactor::context([
            'action' => $action,
            'duration_ms' => $this->durationMs($startedAt),
        ] + $context));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function logDomainFailure(string $action, MenuDomainException $exception, float $startedAt, array $context = []): void
    {
        LogContext::refreshRuntimeContext('menu');

        Log::warning('action failed', Redactor::context([
            'action' => $action,
            'error_code' => $exception->errorCode(),
            'duration_ms' => $this->durationMs($startedAt),
        ] + $context));
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    private function auditMenuMutation(string $action, string $targetType, int $targetId, ?array $before, ?array $after): void
    {
        LogContext::refreshRuntimeContext('menu');

        app(AuditRecorder::class)->record($action, $targetType, $targetId, $before, $after);
    }

    /**
     * @return array<string, mixed>
     */
    private function menuCategoryAuditPayload(MenuCategory $category): array
    {
        return [
            'id' => (int) $category->id,
            'parent_id' => $this->nullableInt($category->parent_id),
            'archived_with_category_id' => $this->nullableInt($category->archived_with_category_id),
            'translated_name' => $category->getAttribute('translated_name'),
            'sort_order' => (int) $category->sort_order,
            'active' => (bool) $category->active,
            'deleted_at' => $this->dateAuditValue($category->deleted_at),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function menuItemAuditPayload(MenuItem $item): array
    {
        return [
            'id' => (int) $item->id,
            'branch_id' => (int) $item->branch_id,
            'category_id' => (int) $item->category_id,
            'archived_with_category_id' => $this->nullableInt($item->archived_with_category_id),
            'translated_name' => $item->getAttribute('translated_name'),
            'translated_description' => $item->getAttribute('translated_description'),
            'price_minor' => (int) $item->price_minor,
            'currency' => (string) $item->currency,
            'sort_order' => (int) $item->sort_order,
            'active' => (bool) $item->active,
            'internal_image' => $item->getAttribute('internal_image'),
            'public_image' => $item->getAttribute('public_image'),
            'deleted_at' => $this->dateAuditValue($item->deleted_at),
        ];
    }

    private function durationMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }

    private function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function dateAuditValue(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }

        return is_string($value) && $value !== '' ? $value : null;
    }
}
