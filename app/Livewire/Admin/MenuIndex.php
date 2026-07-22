<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Modules\Menu\Application\PaginateMenuCategories;
use App\Modules\Menu\Application\PaginateMenuItems;
use App\Modules\Menu\Application\SearchMenuItems;
use App\Modules\Menu\Infrastructure\Models\MenuCategory;
use App\Modules\Menu\Infrastructure\Storage\MenuItemImageUrlResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;

final class MenuIndex extends Component
{
    private const ARCHIVE_MODE_ACTIVE = 'active';

    private const ARCHIVE_MODE_ARCHIVED = 'archived';

    private const ARCHIVE_MODE_ALL = 'all';

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

    public function mount(): void
    {
        $this->normalizeState();
    }

    public function render(
        PaginateMenuCategories $categories,
        PaginateMenuItems $items,
        SearchMenuItems $searchItems,
        MenuItemImageUrlResolver $imageUrls,
    ): View {
        $this->normalizeState();
        $selectedCategoryId = $this->selectedCategoryId();
        $isSearching = trim($this->search) !== '';
        $categoryPage = $categories(
            search: $this->categorySearch,
            archiveMode: $this->archiveMode(),
            perPage: self::CATEGORY_PAGE_SIZE,
            page: $this->categoryPage,
        );
        $itemPage = $selectedCategoryId === null
            ? null
            : $items(
                categoryId: $selectedCategoryId,
                includeInactive: $this->showInactive,
                archiveMode: $this->archiveMode(),
                perPage: self::ITEM_PAGE_SIZE,
                page: $this->itemPage,
            );
        $globalResults = $isSearching
            ? $searchItems(
                search: $this->search,
                includeInactive: $this->showInactive,
                archiveMode: $this->archiveMode(),
                perPage: self::ITEM_PAGE_SIZE,
                page: $this->searchPage,
            )
            : null;

        return view('livewire.admin.menu-index', [
            'archiveMode' => $this->archiveMode(),
            'canManageCategories' => auth()->user()?->can('menu.categories.manage') ?? false,
            'canManageItems' => auth()->user()?->can('menu.items.manage') ?? false,
            'canViewArchive' => $this->canViewArchive(),
            'categories' => $categoryPage,
            'globalResults' => $globalResults,
            'imageUrls' => $imageUrls,
            'isSearching' => $isSearching,
            'items' => $itemPage,
            'selectedCategory' => $selectedCategoryId === null ? null : $this->findCategory($selectedCategoryId),
            'selectedCategoryId' => $selectedCategoryId,
        ]);
    }

    public function selectCategory(int $categoryId): void
    {
        $category = $this->findCategory($categoryId);

        if (! $category instanceof MenuCategory) {
            return;
        }

        $this->category = (int) $category->id;
        $this->search = '';
        $this->itemPage = 1;
        $this->searchPage = 1;
    }

    public function clearSearch(): void
    {
        $this->search = '';
        $this->searchPage = 1;
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
        $this->normalizeArchiveMode();
        $this->categoryPage = 1;
        $this->itemPage = 1;
        $this->searchPage = 1;
    }

    private function normalizeState(): void
    {
        $this->categoryPage = max(1, $this->categoryPage);
        $this->itemPage = max(1, $this->itemPage);
        $this->searchPage = max(1, $this->searchPage);

        $this->normalizeArchiveMode();

        $selectedCategoryId = $this->selectedCategoryId();

        if ($this->category !== $selectedCategoryId) {
            $this->category = $selectedCategoryId;
        }
    }

    private function selectedCategoryId(): ?int
    {
        if ($this->category !== null && $this->findCategory($this->category) instanceof MenuCategory) {
            return $this->category;
        }

        $query = MenuCategory::query();
        $this->applyCategoryArchiveMode($query);

        /** @var int|null $categoryId */
        $categoryId = $query
            ->orderBy('sort_order')
            ->orderBy('id')
            ->value('id');

        return $categoryId;
    }

    private function findCategory(int $categoryId): ?MenuCategory
    {
        $query = MenuCategory::query();
        $this->applyCategoryArchiveMode($query);

        return $query->find($categoryId);
    }

    private function normalizeArchiveMode(): void
    {
        if (! in_array($this->archiveMode, $this->archiveModes(), true)) {
            $this->archiveMode = self::ARCHIVE_MODE_ACTIVE;
        }

        if (! $this->canViewArchive() && $this->archiveMode !== self::ARCHIVE_MODE_ACTIVE) {
            $this->archiveMode = self::ARCHIVE_MODE_ACTIVE;
        }
    }

    /**
     * @return 'active'|'archived'|'all'
     */
    private function archiveMode(): string
    {
        $this->normalizeArchiveMode();

        /** @var 'active'|'archived'|'all' $archiveMode */
        $archiveMode = $this->archiveMode;

        return $archiveMode;
    }

    /**
     * @return list<'active'|'archived'|'all'>
     */
    private function archiveModes(): array
    {
        return [
            self::ARCHIVE_MODE_ACTIVE,
            self::ARCHIVE_MODE_ARCHIVED,
            self::ARCHIVE_MODE_ALL,
        ];
    }

    /**
     * @param  Builder<MenuCategory>  $query
     * @return Builder<MenuCategory>
     */
    private function applyCategoryArchiveMode(Builder $query): Builder
    {
        match ($this->archiveMode()) {
            self::ARCHIVE_MODE_ACTIVE => null,
            self::ARCHIVE_MODE_ARCHIVED => $query
                ->withTrashed()
                ->where(
                    fn (Builder $query): Builder => $query
                        ->whereNotNull('deleted_at')
                        ->orWhereHas('items', fn (Builder $query): Builder => $query->onlyTrashed()),
                ),
            self::ARCHIVE_MODE_ALL => $query->withTrashed(),
        };

        return $query;
    }

    private function canViewArchive(): bool
    {
        return (bool) data_get(auth()->user(), 'is_superadmin');
    }
}
