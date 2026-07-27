<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Modules\Menu\Contracts\MenuCatalog;
use App\Modules\Menu\Contracts\SellableMenuBrowseResult;
use App\Modules\Menu\Contracts\SellableMenuCategory;
use App\Modules\Menu\Contracts\SellableMenuCategoryGroup;
use App\Modules\Menu\Contracts\SellableMenuItem;
use App\Modules\Orders\Application\FindOrderWorkspace;
use App\Modules\Orders\Application\OrderWorkspace as OrderWorkspaceData;
use App\Modules\Orders\Application\OrderWorkspaceItem;
use App\Modules\Orders\Application\OrderWorkspaceSubtable;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Support\I18n\LocalizedText;
use App\Support\Money\Money;
use App\Support\Money\MoneyFormatter;
use Illuminate\Contracts\View\View;
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

    public function mount(int $orderId): void
    {
        abort_unless(auth()->user()?->can('orders.take') ?? false, 403);

        $this->orderId = $orderId;
    }

    public function render(): View
    {
        $workspace = app(FindOrderWorkspace::class)($this->orderId);
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

        return view('livewire.admin.order-workspace', [
            'menu' => $this->menu($menu),
            'order' => $this->order($workspace),
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
}
