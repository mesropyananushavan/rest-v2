<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Modules\Orders\Application\FindOrderWorkspace;
use App\Modules\Orders\Application\OrderWorkspace as OrderWorkspaceData;
use App\Modules\Orders\Application\OrderWorkspaceItem;
use App\Modules\Orders\Application\OrderWorkspaceSubtable;
use App\Support\I18n\LocalizedText;
use App\Support\Money\Money;
use App\Support\Money\MoneyFormatter;
use Illuminate\Contracts\View\View;
use Livewire\Component;

final class OrderWorkspace extends Component
{
    public int $orderId;

    public function mount(int $orderId): void
    {
        abort_unless(auth()->user()?->can('orders.take') ?? false, 403);

        $this->orderId = $orderId;
    }

    public function render(): View
    {
        $workspace = app(FindOrderWorkspace::class)($this->orderId);

        return view('livewire.admin.order-workspace', [
            'order' => $this->order($workspace),
        ]);
    }

    /**
     * @return array{
     *     id: int,
     *     type: string,
     *     status: string,
     *     table_id: int,
     *     opened_at: string,
     *     client_count: int,
     *     comment: string|null,
     *     subtotal: string,
     *     discount: string,
     *     total: string,
     *     groups: list<array{id: int|null, name: string, items: list<array{id: int, name: string, qty: int, unit_price: string, discount: string, total: string}>}>
     * }
     */
    private function order(OrderWorkspaceData $workspace): array
    {
        $locale = app()->getLocale();

        return [
            'id' => $workspace->id,
            'type' => $workspace->type,
            'status' => $workspace->status,
            'table_id' => $workspace->tableId,
            'opened_at' => $workspace->openedAt->format('Y-m-d H:i'),
            'client_count' => $workspace->clientCount,
            'comment' => $workspace->comment,
            'subtotal' => $this->money($workspace->subtotalMinor, $workspace->currency, $locale),
            'discount' => $this->money($workspace->discountMinor, $workspace->currency, $locale),
            'total' => $this->money($workspace->totalMinor, $workspace->currency, $locale),
            'groups' => $this->groups($workspace->subtables, $workspace->items, $locale),
        ];
    }

    /**
     * @param  list<OrderWorkspaceSubtable>  $subtables
     * @param  list<OrderWorkspaceItem>  $items
     * @return list<array{id: int|null, name: string, items: list<array{id: int, name: string, qty: int, unit_price: string, discount: string, total: string}>}>
     */
    private function groups(array $subtables, array $items, string $locale): array
    {
        $itemsBySubtable = [];

        foreach ($items as $item) {
            $itemsBySubtable[$item->subtableId ?? 0][] = $this->item($item, $locale);
        }

        $groups = [];

        foreach ($subtables as $subtable) {
            $groups[] = [
                'id' => $subtable->id,
                'name' => $subtable->name,
                'items' => $itemsBySubtable[$subtable->id] ?? [],
            ];
        }

        if (isset($itemsBySubtable[0])) {
            $groups[] = [
                'id' => null,
                'name' => __('orders.workspace.unassigned_items'),
                'items' => $itemsBySubtable[0],
            ];
        }

        return $groups;
    }

    /**
     * @return array{id: int, name: string, qty: int, unit_price: string, discount: string, total: string}
     */
    private function item(OrderWorkspaceItem $item, string $locale): array
    {
        return [
            'id' => $item->id,
            'name' => $this->itemName($item, $locale),
            'qty' => $item->qty,
            'unit_price' => $this->money($item->unitPriceMinor, $item->currency, $locale),
            'discount' => $this->money($item->discountMinor, $item->currency, $locale),
            'total' => $this->money($item->totalMinor, $item->currency, $locale),
        ];
    }

    private function itemName(OrderWorkspaceItem $item, string $locale): string
    {
        if ($item->nameSnapshot === null) {
            return __('orders.workspace.item_name_missing');
        }

        return LocalizedText::fromArray($item->nameSnapshot)->forLocale($locale, 'en');
    }

    private function money(int $minor, string $currency, string $locale): string
    {
        return MoneyFormatter::format(new Money($minor, $currency), $locale);
    }
}
