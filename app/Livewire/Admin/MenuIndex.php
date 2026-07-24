<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Modules\Menu\Application\BrowseMenuItems;
use App\Modules\Menu\Application\ToggleMenuItemActivity;
use App\Modules\Menu\Http\MenuIndexContext;
use App\Modules\Menu\Infrastructure\Models\MenuCategory;
use App\Modules\Menu\Infrastructure\Storage\MenuItemImageUrlResolver;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

final class MenuIndex extends Component
{
    private const ARCHIVE_MODE_ACTIVE = 'active';

    private const CATEGORY_PAGE_SIZE = 25;

    private const ITEM_PAGE_SIZE = 25;

    #[Url(as: 'category', history: true, nullable: true)]
    public ?int $category = null;

    #[Url(as: 'q', history: true, except: '')]
    public string $search = '';

    public string $categorySearch = '';

    #[Url(as: 'category_page', history: true, except: 1)]
    public int $categoryPage = 1;

    #[Url(as: 'item_page', history: true, except: 1)]
    public int $itemPage = 1;

    #[Url(as: 'search_page', history: true, except: 1)]
    public int $searchPage = 1;

    #[Url(as: 'show_inactive', history: true, except: false)]
    public bool $showInactive = false;

    #[Url(as: 'archive_mode', history: true, except: self::ARCHIVE_MODE_ACTIVE)]
    public string $archiveMode = self::ARCHIVE_MODE_ACTIVE;

    public bool $hasExplicitCategoryContext = false;

    public ?string $statusMessage = null;

    public function mount(): void
    {
        $this->hasExplicitCategoryContext = $this->category !== null;
        $this->normalizeState();
    }

    public function render(
        BrowseMenuItems $browseItems,
        MenuItemImageUrlResolver $imageUrls,
    ): View {
        $this->normalizeState();
        $readModel = $browseItems->forMenuIndex(
            categoryId: $this->category,
            search: $this->search,
            categorySearch: $this->categorySearch,
            includeInactive: $this->showInactive,
            archiveMode: $this->archiveMode,
            canViewArchive: $this->canViewArchive(),
            categoryPerPage: self::CATEGORY_PAGE_SIZE,
            itemPerPage: self::ITEM_PAGE_SIZE,
            categoryPage: $this->categoryPage,
            itemPage: $this->itemPage,
            searchPage: $this->searchPage,
        );
        $selectedCategoryId = $readModel->selectedCategoryId();
        $this->archiveMode = $readModel->archiveMode;

        if ($this->category !== $selectedCategoryId) {
            $this->category = $selectedCategoryId;
        }

        return view('livewire.admin.menu-index', [
            'archiveMode' => $readModel->archiveMode,
            'canManageCategories' => auth()->user()?->can('menu.categories.manage') ?? false,
            'canManageItems' => auth()->user()?->can('menu.items.manage') ?? false,
            'canViewArchive' => $this->canViewArchive(),
            'categories' => $readModel->categories,
            'globalResults' => $readModel->globalResults,
            'imageUrls' => $imageUrls,
            'isSearching' => $readModel->isSearching,
            'items' => $readModel->items,
            'menuContext' => MenuIndexContext::fromState(
                category: $this->hasExplicitCategoryContext ? $selectedCategoryId : null,
                categoryPage: $this->categoryPage,
                itemPage: $this->itemPage,
                searchPage: $this->searchPage,
                search: $this->search,
                showInactive: $this->showInactive,
                archiveMode: $readModel->archiveMode,
            )->toFormQuery(),
            'selectedCategory' => $readModel->selectedCategory,
            'selectedCategoryId' => $selectedCategoryId,
        ]);
    }

    public function selectCategory(int $categoryId): void
    {
        $category = app(BrowseMenuItems::class)->selectedCategoryForMenuIndex(
            $categoryId,
            $this->archiveMode,
            $this->canViewArchive(),
        );

        if (! $category instanceof MenuCategory || (int) $category->id !== $categoryId) {
            return;
        }

        $this->category = (int) $category->id;
        $this->hasExplicitCategoryContext = true;
        $this->search = '';
        $this->itemPage = 1;
        $this->searchPage = 1;
    }

    public function clearSearch(): void
    {
        $this->search = '';
        $this->searchPage = 1;
    }

    public function toggleItemActivity(int $itemId, ToggleMenuItemActivity $toggleItemActivity): void
    {
        abort_unless(auth()->user()?->can('menu.items.manage') ?? false, 403);

        $item = $toggleItemActivity($itemId);

        $this->statusMessage = $item->active ? __('menu.flash.item_activated') : __('menu.flash.item_deactivated');
    }

    public function previousCategoryPage(): void
    {
        $this->categoryPage = max(1, $this->categoryPage - 1);
    }

    public function nextCategoryPage(): void
    {
        $this->categoryPage++;
    }

    public function previousItemPage(): void
    {
        $this->itemPage = max(1, $this->itemPage - 1);
    }

    public function nextItemPage(): void
    {
        $this->itemPage++;
    }

    public function previousSearchPage(): void
    {
        $this->searchPage = max(1, $this->searchPage - 1);
    }

    public function nextSearchPage(): void
    {
        $this->searchPage++;
    }

    public function updatedSearch(): void
    {
        $this->searchPage = 1;
    }

    public function updatedCategorySearch(): void
    {
        $this->categoryPage = 1;
    }

    public function updatedShowInactive(): void
    {
        $this->itemPage = 1;
        $this->searchPage = 1;
    }

    public function updatedArchiveMode(): void
    {
        $this->categoryPage = 1;
        $this->itemPage = 1;
        $this->searchPage = 1;
    }

    private function normalizeState(): void
    {
        $this->categoryPage = max(1, $this->categoryPage);
        $this->itemPage = max(1, $this->itemPage);
        $this->searchPage = max(1, $this->searchPage);
    }

    private function canViewArchive(): bool
    {
        return (bool) data_get(auth()->user(), 'is_superadmin');
    }
}
