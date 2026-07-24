<?php

declare(strict_types=1);

namespace App\Modules\Menu\Http;

use App\Modules\Menu\Application\BrowseMenuItems;
use App\Modules\Menu\Infrastructure\Models\MenuCategory;
use Illuminate\Http\Request;

final readonly class MenuIndexContext
{
    private const DEFAULT_ARCHIVE_MODE = 'active';

    /**
     * @var list<'active'|'archived'|'all'>
     */
    private const ARCHIVE_MODES = ['active', 'archived', 'all'];

    public function __construct(
        public ?int $category,
        public int $categoryPage,
        public int $itemPage,
        public int $searchPage,
        public ?string $search,
        public bool $showInactive,
        public string $archiveMode,
    ) {}

    public static function fromRequest(Request $request, BrowseMenuItems $browseItems, string $defaultArchiveMode = self::DEFAULT_ARCHIVE_MODE): self
    {
        $context = $request->input('context', $request->query('context', []));
        /** @var array<string, mixed> $input */
        $input = is_array($context) ? $context : [];

        foreach (['category', 'category_page', 'item_page', 'search_page', 'q', 'show_inactive', 'archive_mode'] as $key) {
            if (array_key_exists($key, $input)) {
                continue;
            }

            $value = $request->input($key, $request->query($key));

            if ($value !== null) {
                $input[$key] = $value;
            }
        }

        return self::fromInput($input, $browseItems, (bool) data_get($request->user(), 'is_superadmin'), $defaultArchiveMode);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromInput(array $input, BrowseMenuItems $browseItems, bool $canViewArchive, string $defaultArchiveMode = self::DEFAULT_ARCHIVE_MODE): self
    {
        $archiveMode = self::archiveMode($input['archive_mode'] ?? null, $canViewArchive, $defaultArchiveMode);
        $requestedCategory = self::positiveInt($input['category'] ?? null);
        $category = null;

        if ($requestedCategory !== null) {
            $selectedCategory = $browseItems->selectedCategoryForMenuIndex($requestedCategory, $archiveMode, $canViewArchive);
            $category = $selectedCategory instanceof MenuCategory ? (int) $selectedCategory->id : null;
        }

        return new self(
            category: $category,
            categoryPage: self::page($input['category_page'] ?? null),
            itemPage: self::page($input['item_page'] ?? null),
            searchPage: self::page($input['search_page'] ?? null),
            search: self::search($input['q'] ?? null),
            showInactive: self::bool($input['show_inactive'] ?? null),
            archiveMode: $archiveMode,
        );
    }

    public static function fromState(
        ?int $category,
        int $categoryPage,
        int $itemPage,
        int $searchPage,
        string $search,
        bool $showInactive,
        string $archiveMode,
    ): self {
        return new self(
            category: $category,
            categoryPage: max(1, $categoryPage),
            itemPage: max(1, $itemPage),
            searchPage: max(1, $searchPage),
            search: self::search($search),
            showInactive: $showInactive,
            archiveMode: in_array($archiveMode, self::ARCHIVE_MODES, true) ? $archiveMode : self::DEFAULT_ARCHIVE_MODE,
        );
    }

    /**
     * @return array<string, int|string>
     */
    public function toQuery(): array
    {
        $query = [];

        if ($this->category !== null) {
            $query['category'] = $this->category;
        }

        if ($this->search !== null) {
            $query['q'] = $this->search;
        }

        if ($this->categoryPage > 1) {
            $query['category_page'] = $this->categoryPage;
        }

        if ($this->itemPage > 1) {
            $query['item_page'] = $this->itemPage;
        }

        if ($this->searchPage > 1) {
            $query['search_page'] = $this->searchPage;
        }

        if ($this->showInactive) {
            $query['show_inactive'] = 1;
        }

        if ($this->archiveMode !== self::DEFAULT_ARCHIVE_MODE) {
            $query['archive_mode'] = $this->archiveMode;
        }

        return $query;
    }

    /**
     * @return array{context: array<string, int|string>}|array{}
     */
    public function toFormQuery(): array
    {
        $query = $this->toQuery();

        return $query === [] ? [] : ['context' => $query];
    }

    public function url(): string
    {
        return route('admin.menu.index', $this->toQuery());
    }

    private static function page(mixed $value): int
    {
        $integer = self::positiveInt($value);

        return $integer ?? 1;
    }

    private static function positiveInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (! is_string($value) || ! ctype_digit($value)) {
            return null;
        }

        $integer = (int) $value;

        return $integer > 0 ? $integer : null;
    }

    private static function search(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $search = trim($value);

        if ($search === '') {
            return null;
        }

        return mb_substr($search, 0, 120);
    }

    private static function bool(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOL);
    }

    private static function archiveMode(mixed $value, bool $canViewArchive, string $defaultArchiveMode): string
    {
        $default = in_array($defaultArchiveMode, self::ARCHIVE_MODES, true) ? $defaultArchiveMode : self::DEFAULT_ARCHIVE_MODE;
        $archiveMode = is_string($value) && in_array($value, self::ARCHIVE_MODES, true) ? $value : $default;

        if (! $canViewArchive && $archiveMode !== self::DEFAULT_ARCHIVE_MODE) {
            return self::DEFAULT_ARCHIVE_MODE;
        }

        return $archiveMode;
    }
}
