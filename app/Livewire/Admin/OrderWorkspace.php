<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Modules\Identity\Contracts\BranchAssignableUser;
use App\Modules\Identity\Contracts\UserDirectory;
use App\Modules\Menu\Contracts\MenuCatalog;
use App\Modules\Menu\Contracts\SellableMenuBrowseResult;
use App\Modules\Menu\Contracts\SellableMenuCategory;
use App\Modules\Menu\Contracts\SellableMenuCategoryGroup;
use App\Modules\Menu\Contracts\SellableMenuItem;
use App\Modules\Orders\Application\AddItem;
use App\Modules\Orders\Application\AddSubtable;
use App\Modules\Orders\Application\AssignWaiter;
use App\Modules\Orders\Application\CancelOrder;
use App\Modules\Orders\Application\ChangeItemQty;
use App\Modules\Orders\Application\FindOrderWorkspace;
use App\Modules\Orders\Application\MoveItem;
use App\Modules\Orders\Application\OrderWorkspace as OrderWorkspaceData;
use App\Modules\Orders\Application\OrderWorkspaceItem;
use App\Modules\Orders\Application\OrderWorkspaceSubtable;
use App\Modules\Orders\Application\RemoveItem;
use App\Modules\Orders\Contracts\OrderPermissions;
use App\Modules\Orders\Domain\OrdersDomainException;
use App\Modules\Orders\Infrastructure\Models\OrderItem;
use App\Modules\Orders\Infrastructure\Models\OrderSubtable;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Support\I18n\LocalizedText;
use App\Support\Money\Money;
use App\Support\Money\MoneyFormatter;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Lang;
use Livewire\Attributes\Url;
use Livewire\Component;

final class OrderWorkspace extends Component
{
    private const int MENU_ITEM_PAGE_SIZE = 12;

    private const int MENU_CATEGORY_PAGE_SIZE = 6;

    private const int SUBTABLE_NAME_MAX_LENGTH = 60;

    private const string MOVE_TARGET_WITHOUT_SUBTABLE = 'without_subtable';

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

    public string $newSubtableName = '';

    public string $selectedWaiterId = '';

    /**
     * @var array<int, string>
     */
    public array $moveTargetSubtableIds = [];

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
        } catch (OrdersDomainException $exception) {
            $this->errorMessage ??= $this->domainErrorMessage($exception);

            return view('livewire.admin.order-workspace', [
                'menu' => $this->emptyMenu(),
                'order' => $this->staleUnavailableOrder(),
            ]);
        } catch (ModelNotFoundException $exception) {
            if (! $this->workspaceLoaded || $this->lastOrder === [] || $this->lastMenu === []) {
                throw $exception;
            }

            return view('livewire.admin.order-workspace', [
                'menu' => $this->emptyMenu(),
                'order' => $this->staleUnavailableOrder(),
            ]);
        }

        $branchId = $this->branchId();
        $assignableWaiters = app(UserDirectory::class)
            ->activeUsersAssignedToBranchWithPermission($branchId, OrderPermissions::TAKE);
        $this->syncSelectedWaiterId($workspace->assignedWaiterId);

        $menu = app(MenuCatalog::class)->browseSellableInBranch(
            branchId: $branchId,
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
        $menuData = $this->menu($menu);
        $orderData = $this->order($workspace, $assignableWaiters);
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

    public function createSubtable(): void
    {
        $this->authorizeTakingOrders();
        $this->resetFeedback();

        $name = $this->validatedSubtableName();

        if ($name === null) {
            return;
        }

        $workspace = $this->openWorkspaceForMutation();

        if (! $workspace instanceof OrderWorkspaceData) {
            return;
        }

        if ($this->subtableNameExists($workspace, $name)) {
            $this->errorMessage = __('orders.workspace.validation.subtable_name_duplicate');

            return;
        }

        try {
            app(AddSubtable::class)($this->orderId, $name);
        } catch (OrdersDomainException $exception) {
            $this->errorMessage = $this->domainErrorMessage($exception);

            return;
        } catch (ModelNotFoundException) {
            $this->errorMessage = __('orders.order_not_open');

            return;
        }

        $this->newSubtableName = '';
        $this->statusMessage = __('orders.flash.subtable_added');
    }

    public function moveLineToSelectedSubtable(int $orderItemId): void
    {
        $this->authorizeTakingOrders();
        $this->resetFeedback();

        $targetSubtableId = $this->selectedMoveTargetSubtableId($orderItemId);

        if ($this->errorMessage !== null) {
            return;
        }

        try {
            app(MoveItem::class)(
                orderItemId: $orderItemId,
                targetOrderId: null,
                targetSubtableId: $targetSubtableId,
            );
        } catch (OrdersDomainException $exception) {
            $this->errorMessage = $this->domainErrorMessage($exception);

            return;
        } catch (ModelNotFoundException) {
            $this->errorMessage = __('orders.item_not_in_order');

            return;
        }

        unset($this->moveTargetSubtableIds[$orderItemId]);
        $this->statusMessage = __('orders.flash.item_moved');
    }

    public function assignWaiter(): void
    {
        $this->authorizeTakingOrders();
        $this->resetFeedback();

        $waiterId = $this->validatedSelectedWaiterId();

        if ($waiterId === null) {
            return;
        }

        try {
            app(AssignWaiter::class)($this->orderId, $waiterId);
        } catch (OrdersDomainException $exception) {
            $this->errorMessage = $this->domainErrorMessage($exception);

            return;
        } catch (ModelNotFoundException) {
            $this->errorMessage = __('orders.workspace.errors.generic');

            return;
        }

        $this->selectedWaiterId = (string) $waiterId;
        $this->statusMessage = __('orders.flash.waiter_assigned');
    }

    public function clearWaiter(): void
    {
        $this->authorizeTakingOrders();
        $this->resetFeedback();

        try {
            app(AssignWaiter::class)($this->orderId, null);
        } catch (OrdersDomainException $exception) {
            $this->errorMessage = $this->domainErrorMessage($exception);

            return;
        } catch (ModelNotFoundException) {
            $this->errorMessage = __('orders.workspace.errors.generic');

            return;
        }

        $this->selectedWaiterId = '';
        $this->statusMessage = __('orders.flash.waiter_cleared');
    }

    public function cancelOrder(): void
    {
        $this->authorizeTakingOrders();
        $this->resetFeedback();

        try {
            app(CancelOrder::class)($this->orderId);
        } catch (OrdersDomainException $exception) {
            $this->errorMessage = $this->domainErrorMessage($exception);

            return;
        } catch (ModelNotFoundException) {
            $this->errorMessage = __('orders.workspace.errors.generic');

            return;
        }

        session()->flash('status', __('orders.flash.cancelled'));
        $this->redirectRoute('admin.orders.board');
    }

    /**
     * @param  list<BranchAssignableUser>  $assignableWaiters
     * @return array{
     *     id: int,
     *     type: string,
     *     status: string,
     *     table_id: int,
     *     assigned_waiter_id: int|null,
     *     assigned_waiter_name: string,
     *     waiter_options: list<array{id: int, name: string}>,
     *     opened_at: string,
     *     client_count: int,
     *     comment: string|null,
     *     subtotal: string,
     *     discount: string,
     *     total: string,
     *     can_mutate: bool,
     *     stale_unavailable: bool,
     *     line_count: int,
     *     cancel_confirmation_message: string,
     *     subtables: list<array{id: int, name: string}>,
     *     groups: list<array{id: int|null, name: string, items: list<array{id: int, current_subtable_id: int|null, name: string, qty: int, unit_price: string, discount: string, total: string, move_targets: list<array{value: string, label: string}>}>}>
     * }
     */
    private function order(OrderWorkspaceData $workspace, array $assignableWaiters): array
    {
        $locale = app()->getLocale();
        $lineCount = count($workspace->items);

        return [
            'id' => $workspace->id,
            'type' => $workspace->type,
            'status' => $workspace->status,
            'table_id' => $workspace->tableId,
            'assigned_waiter_id' => $workspace->assignedWaiterId,
            'assigned_waiter_name' => $this->assignedWaiterName($workspace->assignedWaiterId, $assignableWaiters),
            'waiter_options' => $this->waiterOptions($assignableWaiters),
            'opened_at' => $workspace->openedAt->format('Y-m-d H:i'),
            'client_count' => $workspace->clientCount,
            'comment' => $workspace->comment,
            'subtotal' => $this->money($workspace->subtotalMinor, $workspace->currency, $locale),
            'discount' => $this->money($workspace->discountMinor, $workspace->currency, $locale),
            'total' => $this->money($workspace->totalMinor, $workspace->currency, $locale),
            'can_mutate' => $workspace->status === 'open',
            'stale_unavailable' => false,
            'line_count' => $lineCount,
            'cancel_confirmation_message' => $lineCount > 0
                ? __('orders.workspace.confirm.cancel_order_message_with_lines', ['count' => $lineCount])
                : __('orders.workspace.confirm.cancel_order_message_empty'),
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
     * @return array{
     *     id: int,
     *     type: string,
     *     status: string,
     *     table_id: int,
     *     assigned_waiter_id: int|null,
     *     assigned_waiter_name: string,
     *     waiter_options: list<array{id: int, name: string}>,
     *     opened_at: string,
     *     client_count: int,
     *     comment: string|null,
     *     subtotal: string,
     *     discount: string,
     *     total: string,
     *     can_mutate: bool,
     *     stale_unavailable: bool,
     *     line_count: int,
     *     cancel_confirmation_message: string,
     *     subtables: list<array{id: int, name: string}>,
     *     groups: list<array{id: int|null, name: string, items: list<array{id: int, current_subtable_id: int|null, name: string, qty: int, unit_price: string, discount: string, total: string, move_targets: list<array{value: string, label: string}>}>}>
     * }
     */
    private function staleUnavailableOrder(): array
    {
        return [
            'id' => $this->orderId,
            'type' => '',
            'status' => '',
            'table_id' => 0,
            'assigned_waiter_id' => null,
            'assigned_waiter_name' => __('orders.workspace.waiter.not_assigned'),
            'waiter_options' => [],
            'opened_at' => '',
            'client_count' => 0,
            'comment' => null,
            'subtotal' => '',
            'discount' => '',
            'total' => '',
            'can_mutate' => false,
            'stale_unavailable' => true,
            'line_count' => 0,
            'cancel_confirmation_message' => '',
            'subtables' => [],
            'groups' => [],
        ];
    }

    /**
     * @param  list<OrderWorkspaceSubtable>  $subtables
     * @param  list<OrderWorkspaceItem>  $items
     * @return list<array{id: int|null, name: string, items: list<array{id: int, current_subtable_id: int|null, name: string, qty: int, unit_price: string, discount: string, total: string, move_targets: list<array{value: string, label: string}>}>}>
     */
    private function groups(array $subtables, array $items, string $locale): array
    {
        $itemsBySubtable = [];

        foreach ($items as $item) {
            $itemsBySubtable[$item->subtableId ?? 0][] = $this->item($item, $subtables, $locale);
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
     * @param  list<OrderWorkspaceSubtable>  $subtables
     * @return array{id: int, current_subtable_id: int|null, name: string, qty: int, unit_price: string, discount: string, total: string, move_targets: list<array{value: string, label: string}>}
     */
    private function item(OrderWorkspaceItem $item, array $subtables, string $locale): array
    {
        return [
            'id' => $item->id,
            'current_subtable_id' => $item->subtableId,
            'name' => $this->itemName($item, $locale),
            'qty' => $item->qty,
            'unit_price' => $this->money($item->unitPriceMinor, $item->currency, $locale),
            'discount' => $this->money($item->discountMinor, $item->currency, $locale),
            'total' => $this->money($item->totalMinor, $item->currency, $locale),
            'move_targets' => $this->moveTargets($item->subtableId, $subtables),
        ];
    }

    /**
     * @param  list<OrderWorkspaceSubtable>  $subtables
     * @return list<array{value: string, label: string}>
     */
    private function moveTargets(?int $currentSubtableId, array $subtables): array
    {
        $targets = [];

        if ($currentSubtableId !== null) {
            $targets[] = [
                'value' => self::MOVE_TARGET_WITHOUT_SUBTABLE,
                'label' => __('orders.workspace.unassigned_items'),
            ];
        }

        foreach ($subtables as $subtable) {
            if ($currentSubtableId === $subtable->id) {
                continue;
            }

            $targets[] = [
                'value' => (string) $subtable->id,
                'label' => $subtable->name,
            ];
        }

        return $targets;
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
    private function emptyMenu(): array
    {
        return [
            'search' => $this->menuSearch,
            'selected_category_id' => $this->menuCategoryId,
            'category_page' => $this->menuCategoryPage,
            'has_previous_category_page' => false,
            'has_more_category_pages' => false,
            'item_page' => $this->menuPage,
            'has_previous_item_page' => false,
            'has_more_item_pages' => false,
            'category_groups' => [],
            'items' => [],
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

        return (int) trim($this->targetSubtableId);
    }

    private function validatedSelectedWaiterId(): ?int
    {
        $waiterId = trim($this->selectedWaiterId);

        if ($waiterId === '') {
            $this->errorMessage = __('orders.workspace.validation.waiter_required');

            return null;
        }

        if (! preg_match('/^[1-9][0-9]*$/', $waiterId)) {
            $this->errorMessage = __('orders.workspace.validation.waiter_invalid');

            return null;
        }

        return (int) $waiterId;
    }

    private function validatedSubtableName(): ?string
    {
        $name = trim($this->newSubtableName);

        if ($name === '') {
            $this->errorMessage = __('orders.workspace.validation.subtable_name_required');

            return null;
        }

        if (mb_strlen($name) > self::SUBTABLE_NAME_MAX_LENGTH) {
            $this->errorMessage = __('orders.workspace.validation.subtable_name_max', [
                'max' => self::SUBTABLE_NAME_MAX_LENGTH,
            ]);

            return null;
        }

        return $name;
    }

    private function openWorkspaceForMutation(): ?OrderWorkspaceData
    {
        try {
            return app(FindOrderWorkspace::class)($this->orderId);
        } catch (OrdersDomainException $exception) {
            $this->errorMessage = $this->domainErrorMessage($exception);

            return null;
        } catch (ModelNotFoundException) {
            $this->errorMessage = __('orders.order_not_open');

            return null;
        }
    }

    private function subtableNameExists(OrderWorkspaceData $workspace, string $name): bool
    {
        $normalizedName = mb_strtolower($name);

        return OrderSubtable::query()
            ->where('branch_id', $this->branchId())
            ->where('order_id', $workspace->id)
            ->where('status', 'open')
            ->get(['name'])
            ->contains(
                fn (OrderSubtable $subtable): bool => mb_strtolower(trim((string) $subtable->name)) === $normalizedName,
            );
    }

    private function selectedMoveTargetSubtableId(int $orderItemId): ?int
    {
        if (! array_key_exists($orderItemId, $this->moveTargetSubtableIds)) {
            $this->errorMessage = __('orders.workspace.validation.move_target_required');

            return null;
        }

        $target = $this->moveTargetSubtableIds[$orderItemId];

        if ($target === self::MOVE_TARGET_WITHOUT_SUBTABLE) {
            return null;
        }

        if (! preg_match('/^-?\d+$/', $target)) {
            $this->errorMessage = __('orders.workspace.validation.move_target_invalid');

            return null;
        }

        return (int) $target;
    }

    /**
     * @param  list<BranchAssignableUser>  $waiters
     * @return list<array{id: int, name: string}>
     */
    private function waiterOptions(array $waiters): array
    {
        return array_map(
            fn (BranchAssignableUser $waiter): array => [
                'id' => $waiter->id,
                'name' => $waiter->displayName,
            ],
            $waiters,
        );
    }

    /**
     * @param  list<BranchAssignableUser>  $waiters
     */
    private function assignedWaiterName(?int $assignedWaiterId, array $waiters): string
    {
        if ($assignedWaiterId === null) {
            return __('orders.workspace.waiter.not_assigned');
        }

        foreach ($waiters as $waiter) {
            if ($waiter->id === $assignedWaiterId) {
                return $waiter->displayName;
            }
        }

        return app(UserDirectory::class)->findName($assignedWaiterId)
            ?? __('orders.workspace.waiter.unknown');
    }

    private function syncSelectedWaiterId(?int $assignedWaiterId): void
    {
        if ($this->selectedWaiterId !== '') {
            return;
        }

        $this->selectedWaiterId = $assignedWaiterId === null ? '' : (string) $assignedWaiterId;
    }

    private function resetFeedback(): void
    {
        $this->statusMessage = null;
        $this->errorMessage = null;
    }

    private function authorizeTakingOrders(): void
    {
        abort_unless(auth()->user()?->can(OrderPermissions::TAKE) ?? false, 403);
    }

    private function domainErrorMessage(OrdersDomainException $exception): string
    {
        $code = $exception->errorCode();

        if (Lang::has($code)) {
            return __($code);
        }

        return __('orders.workspace.errors.generic');
    }
}
