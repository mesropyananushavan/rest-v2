<?php

declare(strict_types=1);

namespace App\Modules\Orders\Application;

use DateTimeInterface;

final readonly class OrderWorkspace
{
    /**
     * @param  list<OrderWorkspaceSubtable>  $subtables
     * @param  list<OrderWorkspaceItem>  $items
     */
    public function __construct(
        public int $id,
        public string $type,
        public string $status,
        public int $tableId,
        public ?int $assignedWaiterId,
        public DateTimeInterface $openedAt,
        public int $clientCount,
        public ?string $comment,
        public int $subtotalMinor,
        public int $discountMinor,
        public int $totalMinor,
        public string $currency,
        public array $subtables,
        public array $items,
    ) {}
}
