<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Modules\Menu\Contracts\MenuCatalog;
use App\Modules\Menu\Contracts\SellableMenuBrowseResult;
use App\Modules\Menu\Contracts\SellableMenuCategory;
use App\Modules\Menu\Contracts\SellableMenuCategoryGroup;
use App\Modules\Menu\Contracts\SellableMenuItem;
use App\Modules\Orders\Application\AddItem;
use App\Modules\Orders\Application\ChangeItemQty;
use App\Modules\Orders\Application\FindOrderWorkspace;
use App\Modules\Orders\Application\OrderWorkspace as OrderWorkspaceData;
use App\Modules\Orders\Application\OrderWorkspaceItem;
use App\Modules\Orders\Application\OrderWorkspaceSubtable;
use App\Modules\Orders\Application\RemoveItem;
use App\Modules\Orders\Domain\OrdersDomainException;
use App\Modules\Orders\Infrastructure\Models\OrderItem;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Support\I18n\LocalizedText;
use App\Support\Money\Money;
use App\Support\Money\MoneyFormatter;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Attributes\Url;
use Livewire\Component;

final class OrderWorkspace extends Component
{
    private const int MENU_ITEM_PAGE_SIZE = 12;

    private const int MENU_CATEGORY_PAGE_SIZE = 6;

    public int $orderId;

    #[Url(as: 'menu_q', history: true, except: '')]
    public string $menuSearch = '';

    #[Url(as: 'menu_category', history: true, nullable: true)]
    public ?int $menuCategoryId = null;

    #[Url(as: 'menu_page', history: true, except: 1)]
    public int $menuPage = 1;

    #[Url(as: 'menu_category_page', history: true, except: 1)]
    public int $menuCategoryPage = 1;

    public ?string $targetSubtableId = null;

    public ?string $statusMessage = null;

    public ?string $errorMessage = null;

    public bool $workspaceLoaded = false;

    /**
     * @var array<string, mixed>
     */
    public array $lastOrder = [];

    /**
     * @var array<string, mixed>
     */
    public array $lastMenu = [];

    public function mount(int $orderId): void
    {
        $this->authorizeTakingOrders();

        $this->orderId = $orderId;
    }

    public function render(): View
    {
        try {
            $workspace = app(FindOrderWorkspace::class)($this->orderId);
        } catch (ModelNotFoundException $exception) {
            if (! $this->workspaceLoaded || $this->lastOrder === [] || $this->lastMenu === []) {
                throw $exception;
            }

            $order = $this->lastOrder;
            $order['can_mutate'] = false;

            return view('livewire.admin.order-workspace', [
                'menu' => $this->lastMenu,
                'order' => $order,
            ]);
        }

        $menu = app(MenuCatalog::class)->browseSellableInBranch(
            branchId: $this->branchId(),
            categoryId: $this->menuCategoryId,
            search: $this->menuSearch,
            perPage: self::MENU_ITEM_PAGE_SIZE,
            page: $this->menuPage,
            categoryPerPage: self::MENU_CATEGORY_PAGE_SIZE,
            categoryPage: $this->menuCategoryPage,
        );
        $this->menuCategoryId = $menu->selectedCategoryId;
        $this->menuPage = $menu->itemPage;
        $this->menuCategoryPage = $menu->categoryPage;
        $this->normalizeTargetSubtable($workspace);
        $menuData = $this->menu($menu);
        $orderData = $this->order($workspace);
        $this->lastMenu = $menuData;
        $this->lastOrder = $orderData;
        $this->workspaceLoaded = true;

        return view('livewire.admin.order-workspace', [
            'menu' => $menuData,
            'order' => $orderData,
        ]);
    }

    public function selectMenuCategory(int $categoryId): void
    {
        $this->menuCategoryId = $categoryId > 0 ? $categoryId : null;
        $this->menuPage = 1;
    }

    public function clearMenuCategory(): void
    {
        $this->menuCategoryId = null;
        $this->menuPage = 1;
    }

    public function clearMenuSearch(): void
    {
        $this->menuSearch = '';
        $this->menuPage = 1;
    }

    public function previousMenuPage(): void
    {
        $this->menuPage = max(1, $this->menuPage - 1);
    }

    public function nextMenuPage(): void
    {
        $this->menuPage++;
    }

    public function previousMenuCategoryPage(): void
    {
        $this->menuCategoryPage = max(1, $this->menuCategoryPage - 1);
    }

    public function nextMenuCategoryPage(): void
    {
        $this->menuCategoryPage++;
    }

    public function updatedMenuSearch(): void
    {
        $this->menuPage = 1;
    }

    public function updatedMenuCategoryId(): void
    {
        $this->menuPage = 1;
    }

    public function addMenuItem(int $menuItemId): void
    {
        $this->authorizeTakingOrders();
        $this->resetFeedback();

        try {
            app(AddItem::class)(
                orderId: $this->orderId,
                menuItemId: $menuItemId,
                qty: 1,
                subtableId: $this->selectedTargetSubtableId(),
            );
        } catch (OrdersDomainException $exception) {
            $this->errorMessage = $this->domainErrorMessage($exception);

            return;
        } catch (ModelNotFoundException) {
            $this->errorMessage = __('orders.workspace.errors.generic');

            return;
        }

        $this->statusMessage = __('orders.flash.item_added');
    }

    public function increaseItemQty(int $orderItemId): void
    {
        $this->changeItemQtyBy($orderItemId, 1);
    }

    public function decreaseItemQty(int $orderItemId): void
    {
        $this->changeItemQtyBy($orderItemId, -1);
    }

    public function confirmRemoveItem(int $orderItemId): void
    {
        $this->authorizeTakingOrders();
        $this->resetFeedback();

        try {
            app(RemoveItem::class)($orderItemId);
        } catch (OrdersDomainException $exception) {
            $this->errorMessage = $this->domainErrorMessage($exception);

            return;
        } catch (ModelNotFoundException) {
            $this->errorMessage = __('orders.item_not_in_order');

            return;
        }

        $this->statusMessage = __('orders.flash.item_removed');
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
     *     can_mutate: bool,
     *     subtables: list<array{id: int, name: string}>,
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
            'can_mutate' => $workspace->status === 'open',
            'subtables' => array_map(
                fn (OrderWorkspaceSubtable $subtable): array => [
                    'id' => $subtable->id,
                    'name' => $subtable->name,
                ],
                $workspace->subtables,
            ),
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

    /**
     * @return array{
     *     search: string,
     *     selected_category_id: int|null,
     *     category_page: int,
     *     has_previous_category_page: bool,
     *     has_more_category_pages: bool,
     *     item_page: int,
     *     has_previous_item_page: bool,
     *     has_more_item_pages: bool,
     *     category_groups: list<array{id: int, name: string, categories: list<array{id: int, name: string, selected: bool}>}>,
     *     items: list<array{id: int, category_id: int, name: string, price: string}>
     * }
     */
    private function menu(SellableMenuBrowseResult $menu): array
    {
        $locale = app()->getLocale();

        return [
            'search' => $this->menuSearch,
            'selected_category_id' => $menu->selectedCategoryId,
            'category_page' => $menu->categoryPage,
            'has_previous_category_page' => $menu->categoryPage > 1,
            'has_more_category_pages' => $menu->hasMoreCategoryPages,
            'item_page' => $menu->itemPage,
            'has_previous_item_page' => $menu->itemPage > 1,
            'has_more_item_pages' => $menu->hasMoreItemPages,
            'category_groups' => array_map(
                fn (SellableMenuCategoryGroup $group): array => [
                    'id' => $group->id,
                    'name' => $group->name->forLocale($locale, 'en'),
                    'categories' => array_map(
                        fn (SellableMenuCategory $category): array => [
                            'id' => $category->id,
                            'name' => $category->name->forLocale($locale, 'en'),
                            'selected' => $menu->selectedCategoryId === $category->id,
                        ],
                        $group->categories,
                    ),
                ],
                $menu->categoryGroups,
            ),
            'items' => array_map(
                fn (SellableMenuItem $item): array => [
                    'id' => $item->id,
                    'category_id' => $item->categoryId,
                    'name' => $item->name->forLocale($locale, 'en'),
                    'price' => MoneyFormatter::format($item->price, $locale),
                ],
                $menu->items,
            ),
        ];
    }

    private function branchId(): int
    {
        $branchId = app(BranchContext::class)->id();

        abort_if($branchId === null, 404);

        return $branchId;
    }

    private function changeItemQtyBy(int $orderItemId, int $delta): void
    {
        $this->authorizeTakingOrders();
        $this->resetFeedback();

        $currentQty = $this->currentItemQty($orderItemId);

        if ($currentQty === null) {
            return;
        }

        $nextQty = $currentQty + $delta;

        if ($nextQty < 1 || $nextQty > 999) {
            $this->errorMessage = __('orders.invalid_quantity');

            return;
        }

        try {
            app(ChangeItemQty::class)($orderItemId, $nextQty);
        } catch (OrdersDomainException $exception) {
            $this->errorMessage = $this->domainErrorMessage($exception);

            return;
        } catch (ModelNotFoundException) {
            $this->errorMessage = __('orders.item_not_in_order');

            return;
        }

        $this->statusMessage = __('orders.flash.item_qty_changed');
    }

    private function currentItemQty(int $orderItemId): ?int
    {
        try {
            $item = OrderItem::query()
                ->where('branch_id', $this->branchId())
                ->where('order_id', $this->orderId)
                ->find($orderItemId, ['id', 'qty']);
        } catch (ModelNotFoundException) {
            $this->errorMessage = __('orders.item_not_in_order');

            return null;
        }

        if (! $item instanceof OrderItem) {
            $this->errorMessage = __('orders.item_not_in_order');

            return null;
        }

        return (int) $item->qty;
    }

    private function selectedTargetSubtableId(): ?int
    {
        if ($this->targetSubtableId === null || trim($this->targetSubtableId) === '') {
            return null;
        }

        if (! ctype_digit($this->targetSubtableId)) {
            return null;
        }

        $subtableId = (int) $this->targetSubtableId;

        return $subtableId > 0 ? $subtableId : null;
    }

    private function normalizeTargetSubtable(OrderWorkspaceData $workspace): void
    {
        if ($workspace->subtables === []) {
            $this->targetSubtableId = null;

            return;
        }

        if ($this->targetSubtableId === null || $this->targetSubtableId === '') {
            return;
        }

        $selected = $this->selectedTargetSubtableId();

        foreach ($workspace->subtables as $subtable) {
            if ($subtable->id === $selected) {
                return;
            }
        }

        $this->targetSubtableId = null;
    }

    private function resetFeedback(): void
    {
        $this->statusMessage = null;
        $this->errorMessage = null;
    }

    private function authorizeTakingOrders(): void
    {
        abort_unless(auth()->user()?->can('orders.take') ?? false, 403);
    }

    private function domainErrorMessage(OrdersDomainException $exception): string
    {
        return match ($exception->errorCode()) {
            'orders.tenant_context_required',
            'orders.branch_context_required',
            'orders.order_not_open',
            'orders.menu_item_not_found',
            'orders.currency_mismatch',
            'orders.item_not_in_order',
            'orders.invalid_quantity',
            'orders.subtable_not_in_order' => __($exception->errorCode()),
            default => __('orders.workspace.errors.generic'),
        };
    }
}
