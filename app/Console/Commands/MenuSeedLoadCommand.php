<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use JsonException;
use Throwable;

final class MenuSeedLoadCommand extends Command
{
    private const MODE_PRODUCTION_LIKE = 'production-like';

    private const MODE_GIANT_MENU = 'giant-menu';

    private const MIN_BATCH_SIZE = 5000;

    private const MAX_BATCH_SIZE = 20000;

    /**
     * @var list<string>
     */
    private const EN_ADJECTIVES = [
        'Smoked', 'Garden', 'Crispy', 'Velvet', 'Herbed', 'Charcoal', 'Rustic', 'Golden',
        'Mountain', 'Market', 'Fresh', 'Spiced', 'Roasted', 'Stone', 'Village', 'Seasonal',
    ];

    /**
     * @var list<string>
     */
    private const EN_PROTEINS = [
        'Chicken', 'Beef', 'Lamb', 'Trout', 'Mushroom', 'Eggplant', 'Cheese', 'Beans',
        'Pumpkin', 'Potato', 'Tomato', 'Pepper', 'Spinach', 'Pork', 'Turkey', 'Lentil',
    ];

    /**
     * @var list<string>
     */
    private const EN_STYLES = [
        'Skillet', 'Plate', 'Bowl', 'Soup', 'Salad', 'Kebab', 'Stew', 'Toast',
        'Wrap', 'Cutlet', 'Pilaf', 'Tart', 'Spread', 'Sauce', 'Grill', 'Bake',
    ];

    /**
     * @var list<string>
     */
    private const HY_ADJECTIVES = [
        'Ապխտած', 'Այգու', 'Խրթխրթան', 'Թավշյա', 'Կանաչիով', 'Ածուխի', 'Գյուղական', 'Ոսկեգույն',
        'Լեռնային', 'Շուկայի', 'Թարմ', 'Համեմված', 'Տապակած', 'Քարե', 'Սեզոնային', 'Տնական',
    ];

    /**
     * @var list<string>
     */
    private const HY_PROTEINS = [
        'հավ', 'տավար', 'գառ', 'իշխան', 'սունկ', 'սմբուկ', 'պանիր', 'լոբի',
        'դդում', 'կարտոֆիլ', 'լոլիկ', 'պղպեղ', 'սպանախ', 'խոզ', 'հնդկահավ', 'ոսպ',
    ];

    /**
     * @var list<string>
     */
    private const HY_STYLES = [
        'թավա', 'ափսե', 'աման', 'ապուր', 'աղցան', 'խորոված', 'շոգեխաշ', 'տոստ',
        'ռոլ', 'կոտլետ', 'փլավ', 'կարկանդակ', 'մածուկ', 'սոուս', 'գրիլ', 'թխվածք',
    ];

    /**
     * @var list<string>
     */
    private const RU_ADJECTIVES = [
        'Копчёный', 'Садовый', 'Хрустящий', 'Бархатный', 'Травяной', 'Угольный', 'Деревенский', 'Золотой',
        'Горный', 'Рыночный', 'Свежий', 'Пряный', 'Запечённый', 'Каменный', 'Сезонный', 'Домашний',
    ];

    /**
     * @var list<string>
     */
    private const RU_PROTEINS = [
        'цыплёнок', 'говядина', 'ягнёнок', 'форель', 'грибы', 'баклажан', 'сыр', 'фасоль',
        'тыква', 'картофель', 'томат', 'перец', 'шпинат', 'свинина', 'индейка', 'чечевица',
    ];

    /**
     * @var list<string>
     */
    private const RU_STYLES = [
        'сковорода', 'тарелка', 'боул', 'суп', 'салат', 'кебаб', 'рагу', 'тост',
        'ролл', 'котлета', 'плов', 'тарт', 'паста', 'соус', 'гриль', 'запеканка',
    ];

    protected $signature = 'menu:seed-load
        {--mode=production-like : production-like or giant-menu}
        {--restaurants=300 : Restaurant/tenant count for production-like mode}
        {--categories=20 : Root categories per restaurant, or root categories in giant-menu mode}
        {--subcategories=5 : Subcategories per root category}
        {--items=500 : Items per subcategory}
        {--batch=10000 : Raw insert batch size, 5000-20000}
        {--drop-rebuild-trgm : Drop and recreate menu PostgreSQL GIN trigram indexes around the load}
        {--force : Allow running outside local/testing environments}';

    protected $description = 'Seed large menu datasets for local PostgreSQL load and UI performance testing.';

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing']) && ! (bool) $this->option('force')) {
            $this->error('Refusing to run outside local/testing without --force.');

            return self::FAILURE;
        }

        DB::disableQueryLog();
        @set_time_limit(0);

        $config = $this->config();
        $this->printPlan($config);

        $trgmDropped = false;
        try {
            if ($config['dropRebuildTrgm']) {
                $this->dropTrgmIndexes();
                $trgmDropped = true;
            }

            match ($config['mode']) {
                self::MODE_PRODUCTION_LIKE => $this->seedProductionLike($config),
                self::MODE_GIANT_MENU => $this->seedGiantMenu($config),
            };

            if ($trgmDropped) {
                $this->rebuildTrgmIndexes();
            }
        } catch (Throwable $exception) {
            if ($trgmDropped) {
                $this->warn('Load failed after dropping trgm indexes; rebuilding them before exiting.');
                $this->rebuildTrgmIndexes();
            }

            throw $exception;
        }

        $this->info('menu:seed-load complete.');

        return self::SUCCESS;
    }

    /**
     * @return array{
     *     mode: 'production-like'|'giant-menu',
     *     restaurants: int,
     *     categories: int,
     *     subcategories: int,
     *     items: int,
     *     batch: int,
     *     dropRebuildTrgm: bool,
     *     runId: string
     * }
     */
    private function config(): array
    {
        $mode = (string) $this->option('mode');

        if (! in_array($mode, [self::MODE_PRODUCTION_LIKE, self::MODE_GIANT_MENU], true)) {
            $this->fail('Unsupported --mode. Use production-like or giant-menu.');
        }

        $batch = $this->positiveIntOption('batch');

        if ($batch < self::MIN_BATCH_SIZE || $batch > self::MAX_BATCH_SIZE) {
            $this->fail('--batch must be between 5000 and 20000.');
        }

        /** @var 'production-like'|'giant-menu' $mode */
        return [
            'mode' => $mode,
            'restaurants' => $mode === self::MODE_PRODUCTION_LIKE ? $this->positiveIntOption('restaurants') : 1,
            'categories' => $this->positiveIntOption('categories'),
            'subcategories' => $this->positiveIntOption('subcategories'),
            'items' => $this->positiveIntOption('items'),
            'batch' => $batch,
            'dropRebuildTrgm' => (bool) $this->option('drop-rebuild-trgm'),
            'runId' => now()->format('YmdHis').'-'.getmypid(),
        ];
    }

    private function positiveIntOption(string $name): int
    {
        $value = filter_var($this->option($name), FILTER_VALIDATE_INT);

        if (! is_int($value) || $value < 1) {
            $this->fail("--{$name} must be a positive integer.");
        }

        return $value;
    }

    /**
     * @param  array{
     *     mode: 'production-like'|'giant-menu',
     *     restaurants: int,
     *     categories: int,
     *     subcategories: int,
     *     items: int,
     *     batch: int,
     *     dropRebuildTrgm: bool,
     *     runId: string
     * }  $config
     */
    private function printPlan(array $config): void
    {
        $rootCategories = $config['restaurants'] * $config['categories'];
        $subcategories = $rootCategories * $config['subcategories'];
        $items = $subcategories * $config['items'];

        $this->line('menu:seed-load plan');
        $this->line("mode: {$config['mode']}");
        $this->line("run_id: {$config['runId']}");
        $this->line("restaurants/tenants: {$config['restaurants']}");
        $this->line("branches: {$config['restaurants']}");
        $this->line("root_categories = restaurants * categories = {$config['restaurants']} * {$config['categories']} = ".number_format($rootCategories));
        $this->line('subcategories = root_categories * subcategories = '.number_format($rootCategories)." * {$config['subcategories']} = ".number_format($subcategories));
        $this->line('items = subcategories * items = '.number_format($subcategories)." * {$config['items']} = ".number_format($items));
        $this->line('total menu rows = '.number_format($rootCategories + $subcategories + $items));
        $this->line('batch size: '.number_format($config['batch']));
        $this->line('drop/rebuild trgm: '.($config['dropRebuildTrgm'] ? 'yes' : 'no'));
    }

    /**
     * @param  array{
     *     restaurants: int,
     *     categories: int,
     *     subcategories: int,
     *     items: int,
     *     batch: int,
     *     runId: string
     * }  $config
     */
    private function seedProductionLike(array $config): void
    {
        $tenantIdsByRestaurant = $this->insertTenants($config['restaurants'], $config['runId']);
        $branchIdsByRestaurant = $this->insertBranches($tenantIdsByRestaurant);
        $rootIds = $this->insertRootCategories($tenantIdsByRestaurant, $config['categories'], $config['batch']);
        $subcategoryIds = $this->insertSubcategories($rootIds, $config['subcategories'], $config['batch']);

        $this->insertItems($subcategoryIds, $branchIdsByRestaurant, $config['items'], $config['batch']);
    }

    /**
     * @param  array{
     *     categories: int,
     *     subcategories: int,
     *     items: int,
     *     batch: int,
     *     runId: string
     * }  $config
     */
    private function seedGiantMenu(array $config): void
    {
        $tenantIdsByRestaurant = $this->insertTenants(1, $config['runId']);
        $branchIdsByRestaurant = $this->insertBranches($tenantIdsByRestaurant);
        $rootIds = $this->insertRootCategories($tenantIdsByRestaurant, $config['categories'], $config['batch']);
        $subcategoryIds = $this->insertSubcategories($rootIds, $config['subcategories'], $config['batch']);

        $this->insertItems($subcategoryIds, $branchIdsByRestaurant, $config['items'], $config['batch']);
    }

    /**
     * @return array<int, int>
     */
    private function insertTenants(int $restaurants, string $runId): array
    {
        $timestamp = now();
        $rows = [];

        for ($restaurant = 1; $restaurant <= $restaurants; $restaurant++) {
            $rows[] = [
                'name' => "Load Restaurant {$runId} #{$restaurant}",
                'slug' => "load-{$runId}-restaurant-{$restaurant}",
                'default_locale' => 'hy',
                'currency' => $restaurant % 7 === 0 ? 'USD' : 'AMD',
                'status' => 'active',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }

        $this->insertRowsInBatches('tenants', $rows, self::MAX_BATCH_SIZE);

        /** @var array<int, int> $tenantIdsByRestaurant */
        $tenantIdsByRestaurant = DB::table('tenants')
            ->whereIn('slug', array_column($rows, 'slug'))
            ->pluck('id', 'slug')
            ->mapWithKeys(function (mixed $id, int|string $slug): array {
                $slug = (string) $slug;

                if (preg_match('/restaurant-(\d+)$/', $slug, $matches) !== 1) {
                    return [];
                }

                return [(int) $matches[1] => $this->integerDatabaseValue($id, 'tenant id')];
            })
            ->all();

        if (count($tenantIdsByRestaurant) !== $restaurants) {
            $this->fail('Failed to resolve all inserted tenant ids.');
        }

        $this->info('Inserted tenants: '.number_format(count($tenantIdsByRestaurant)));

        return $tenantIdsByRestaurant;
    }

    /**
     * @param  array<int, int>  $tenantIdsByRestaurant
     * @return array<int, int>
     */
    private function insertBranches(array $tenantIdsByRestaurant): array
    {
        $timestamp = now();
        $rows = [];

        foreach ($tenantIdsByRestaurant as $restaurant => $tenantId) {
            $rows[] = [
                'tenant_id' => $tenantId,
                'name' => "Load Branch #{$restaurant}",
                'address' => null,
                'phone' => null,
                'locale' => null,
                'timezone' => 'Asia/Yerevan',
                'status' => 'active',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }

        $this->insertRowsInBatches('branches', $rows, self::MAX_BATCH_SIZE);

        $tenantToRestaurant = array_flip($tenantIdsByRestaurant);
        /** @var array<int, int> $branchIdsByRestaurant */
        $branchIdsByRestaurant = DB::table('branches')
            ->whereIn('tenant_id', array_values($tenantIdsByRestaurant))
            ->pluck('id', 'tenant_id')
            ->mapWithKeys(fn (mixed $id, int|string $tenantId): array => [
                (int) $tenantToRestaurant[(int) $tenantId] => $this->integerDatabaseValue($id, 'branch id'),
            ])
            ->all();

        if (count($branchIdsByRestaurant) !== count($tenantIdsByRestaurant)) {
            $this->fail('Failed to resolve all inserted branch ids.');
        }

        $this->info('Inserted branches: '.number_format(count($branchIdsByRestaurant)));

        return $branchIdsByRestaurant;
    }

    /**
     * @param  array<int, int>  $tenantIdsByRestaurant
     * @return array<string, int>
     */
    private function insertRootCategories(array $tenantIdsByRestaurant, int $categories, int $batch): array
    {
        $timestamp = now();
        $rows = [];

        foreach ($tenantIdsByRestaurant as $restaurant => $tenantId) {
            for ($category = 1; $category <= $categories; $category++) {
                $rows[] = [
                    'tenant_id' => $tenantId,
                    'parent_id' => null,
                    'archived_with_category_id' => null,
                    'translated_name' => $this->jsonName('Root', $restaurant, $category),
                    'sort_order' => $category,
                    'active' => true,
                    'deleted_at' => null,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];

                if (count($rows) >= $batch) {
                    $this->insertRowsInBatches('menu_categories', $rows, $batch);
                    $rows = [];
                }
            }
        }

        if ($rows !== []) {
            $this->insertRowsInBatches('menu_categories', $rows, $batch);
        }

        $rootIds = [];
        $tenantToRestaurant = array_flip($tenantIdsByRestaurant);

        DB::table('menu_categories')
            ->whereIn('tenant_id', array_values($tenantIdsByRestaurant))
            ->whereNull('parent_id')
            ->select(['id', 'tenant_id', 'sort_order'])
            ->orderBy('id')
            ->chunkById(self::MAX_BATCH_SIZE, function ($categories) use (&$rootIds, $tenantToRestaurant): void {
                foreach ($categories as $category) {
                    $tenantId = $this->integerDatabaseValue($category->tenant_id, 'root tenant id');
                    $sortOrder = $this->integerDatabaseValue($category->sort_order, 'root sort order');
                    $restaurant = (int) $tenantToRestaurant[$tenantId];
                    $rootIds[$this->rootKey($restaurant, $sortOrder)] = $this->integerDatabaseValue($category->id, 'root category id');
                }
            });

        if (count($rootIds) !== count($tenantIdsByRestaurant) * $categories) {
            $this->fail('Failed to resolve all inserted root category ids.');
        }

        $this->info('Inserted root categories: '.number_format(count($rootIds)));

        return $rootIds;
    }

    /**
     * @param  array<string, int>  $rootIds
     * @return array<string, array{id: int, restaurant: int}>
     */
    private function insertSubcategories(array $rootIds, int $subcategories, int $batch): array
    {
        $timestamp = now();
        $rows = [];
        $coordinatesByRootId = [];
        /** @var array<int, int> $tenantIdsByRootId */
        $tenantIdsByRootId = DB::table('menu_categories')
            ->whereIn('id', array_values($rootIds))
            ->pluck('tenant_id', 'id')
            ->mapWithKeys(fn (mixed $tenantId, int|string $rootId): array => [
                (int) $rootId => $this->integerDatabaseValue($tenantId, 'root tenant id'),
            ])
            ->all();

        if (count($tenantIdsByRootId) !== count($rootIds)) {
            $this->fail('Failed to resolve tenant ids for root categories.');
        }

        foreach ($rootIds as $rootKey => $rootId) {
            [$restaurant, $category] = array_map('intval', explode(':', $rootKey));
            $coordinatesByRootId[$rootId] = [$restaurant, $category];

            for ($subcategory = 1; $subcategory <= $subcategories; $subcategory++) {
                $rows[] = [
                    'tenant_id' => $tenantIdsByRootId[$rootId],
                    'parent_id' => $rootId,
                    'archived_with_category_id' => null,
                    'translated_name' => $this->jsonName('Section', $restaurant, $category, $subcategory),
                    'sort_order' => $subcategory,
                    'active' => true,
                    'deleted_at' => null,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];

                if (count($rows) >= $batch) {
                    $this->insertRowsInBatches('menu_categories', $rows, $batch);
                    $rows = [];
                }
            }
        }

        if ($rows !== []) {
            $this->insertRowsInBatches('menu_categories', $rows, $batch);
        }

        $subcategoryIds = [];
        DB::table('menu_categories')
            ->whereIn('parent_id', array_values($rootIds))
            ->select(['id', 'parent_id', 'sort_order'])
            ->orderBy('id')
            ->chunkById(self::MAX_BATCH_SIZE, function ($categories) use (&$subcategoryIds, $coordinatesByRootId): void {
                foreach ($categories as $category) {
                    $parentId = $this->integerDatabaseValue($category->parent_id, 'subcategory parent id');
                    $sortOrder = $this->integerDatabaseValue($category->sort_order, 'subcategory sort order');
                    [$restaurant, $rootCategory] = $coordinatesByRootId[$parentId];
                    $subcategoryIds[$this->subcategoryKey($restaurant, $rootCategory, $sortOrder)] = [
                        'id' => $this->integerDatabaseValue($category->id, 'subcategory id'),
                        'restaurant' => $restaurant,
                    ];
                }
            });

        if (count($subcategoryIds) !== count($rootIds) * $subcategories) {
            $this->fail('Failed to resolve all inserted subcategory ids.');
        }

        $this->info('Inserted subcategories: '.number_format(count($subcategoryIds)));

        return $subcategoryIds;
    }

    /**
     * @param  array<string, array{id: int, restaurant: int}>  $subcategoryIds
     * @param  array<int, int>  $branchIdsByRestaurant
     */
    private function insertItems(array $subcategoryIds, array $branchIdsByRestaurant, int $items, int $batch): void
    {
        $timestamp = now();
        $rows = [];
        $inserted = 0;
        /** @var array<int, int> $tenantIdsByBranchId */
        $tenantIdsByBranchId = DB::table('branches')
            ->whereIn('id', array_values($branchIdsByRestaurant))
            ->pluck('tenant_id', 'id')
            ->mapWithKeys(fn (mixed $tenantId, int|string $branchId): array => [
                (int) $branchId => $this->integerDatabaseValue($tenantId, 'branch tenant id'),
            ])
            ->all();

        if (count($tenantIdsByBranchId) !== count($branchIdsByRestaurant)) {
            $this->fail('Failed to resolve tenant ids for branches.');
        }

        foreach ($subcategoryIds as $subcategoryKey => $subcategory) {
            [$restaurant, $category, $section] = array_map('intval', explode(':', $subcategoryKey));
            $branchId = $branchIdsByRestaurant[$restaurant];
            $tenantId = $tenantIdsByBranchId[$branchId];
            $currency = $restaurant % 7 === 0 ? 'USD' : 'AMD';

            for ($item = 1; $item <= $items; $item++) {
                $rows[] = [
                    'tenant_id' => $tenantId,
                    'branch_id' => $branchId,
                    'category_id' => $subcategory['id'],
                    'translated_name' => $this->jsonName('Dish', $restaurant, $category, $section, $item),
                    'translated_description' => $this->jsonDescription($restaurant, $category, $section, $item),
                    'internal_image' => null,
                    'public_image' => null,
                    'price_minor' => $this->priceMinor($restaurant, $category, $section, $item, $currency),
                    'currency' => $currency,
                    'sort_order' => $item,
                    'active' => $item % 23 !== 0,
                    'archived_with_category_id' => null,
                    'deleted_at' => null,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];

                if (count($rows) >= $batch) {
                    $this->insertRowsInBatches('menu_items', $rows, $batch);
                    $inserted += count($rows);
                    $rows = [];
                    $this->reportItemProgress($inserted);
                }
            }
        }

        if ($rows !== []) {
            $this->insertRowsInBatches('menu_items', $rows, $batch);
            $inserted += count($rows);
        }

        $this->info('Inserted items: '.number_format($inserted));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function insertRowsInBatches(string $table, array $rows, int $batch): void
    {
        if ($batch < 1) {
            $this->fail('Batch size must be positive.');
        }

        /** @var int<1, max> $positiveBatch */
        $positiveBatch = $batch;

        foreach (array_chunk($rows, $positiveBatch) as $chunk) {
            DB::transaction(static function () use ($table, $chunk): void {
                DB::table($table)->insert($chunk);
            });
        }
    }

    private function reportItemProgress(int $inserted): void
    {
        if ($inserted % 1000000 === 0) {
            $this->line('Inserted items so far: '.number_format($inserted));
        }
    }

    private function integerDatabaseValue(mixed $value, string $name): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            return (int) $value;
        }

        $this->fail("Invalid integer database value for {$name}.");
    }

    private function rootKey(int $restaurant, int $category): string
    {
        return "{$restaurant}:{$category}";
    }

    private function subcategoryKey(int $restaurant, int $category, int $subcategory): string
    {
        return "{$restaurant}:{$category}:{$subcategory}";
    }

    /**
     * @throws JsonException
     */
    private function jsonName(string $prefix, int $restaurant, int $category, ?int $subcategory = null, ?int $item = null): string
    {
        $seed = $restaurant * 1000003 + $category * 10007 + ($subcategory ?? 0) * 101 + ($item ?? 0);
        $number = implode('-', array_filter([$restaurant, $category, $subcategory, $item], fn (?int $value): bool => $value !== null));
        $english = $this->localizedGeneratedName(
            self::EN_ADJECTIVES,
            self::EN_PROTEINS,
            self::EN_STYLES,
            $prefix,
            $seed,
            $number,
        );
        $armenian = $this->localizedGeneratedName(
            self::HY_ADJECTIVES,
            self::HY_PROTEINS,
            self::HY_STYLES,
            $this->armenianPrefix($prefix),
            $seed,
            $number,
        );
        $russian = $this->localizedGeneratedName(
            self::RU_ADJECTIVES,
            self::RU_PROTEINS,
            self::RU_STYLES,
            $this->russianPrefix($prefix),
            $seed,
            $number,
        );

        return json_encode([
            'hy' => $armenian,
            'ru' => $russian,
            'en' => $english,
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /**
     * @param  list<string>  $adjectives
     * @param  list<string>  $proteins
     * @param  list<string>  $styles
     */
    private function localizedGeneratedName(
        array $adjectives,
        array $proteins,
        array $styles,
        string $prefix,
        int $seed,
        string $number,
    ): string {
        $adjective = $adjectives[$seed % count($adjectives)];
        $protein = $proteins[intdiv($seed, 3) % count($proteins)];
        $style = $styles[intdiv($seed, 7) % count($styles)];

        return "{$adjective} {$protein} {$style} {$prefix} {$number}";
    }

    private function armenianPrefix(string $prefix): string
    {
        return match ($prefix) {
            'Root' => 'մենյու',
            'Section' => 'բաժին',
            'Dish' => 'ուտեստ',
            default => $prefix,
        };
    }

    private function russianPrefix(string $prefix): string
    {
        return match ($prefix) {
            'Root' => 'меню',
            'Section' => 'раздел',
            'Dish' => 'блюдо',
            default => $prefix,
        };
    }

    /**
     * @throws JsonException
     */
    private function jsonDescription(int $restaurant, int $category, int $subcategory, int $item): string
    {
        $description = "Load generated recipe {$restaurant}.{$category}.{$subcategory}.{$item} with deterministic varied ingredients.";

        return json_encode([
            'hy' => "Բեռնված բաղադրատոմս {$restaurant}.{$category}.{$subcategory}.{$item} տարբեր բաղադրիչներով",
            'ru' => "Нагрузочный рецепт {$restaurant}.{$category}.{$subcategory}.{$item} с разными ингредиентами",
            'en' => $description,
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private function priceMinor(int $restaurant, int $category, int $subcategory, int $item, string $currency): int
    {
        $base = $currency === 'USD' ? 500 : 90000;
        $spread = (($restaurant * 13 + $category * 17 + $subcategory * 19 + $item * 23) % 500);

        return $currency === 'USD'
            ? $base + $spread
            : $base + ($spread * 1000);
    }

    private function dropTrgmIndexes(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            $this->warn('Skipping trgm index drop: current database driver is not pgsql.');

            return;
        }

        $this->warn('Dropping menu trgm indexes.');
        DB::statement('DROP INDEX IF EXISTS menu_items_translated_name_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS menu_categories_translated_name_trgm_idx');
    }

    private function rebuildTrgmIndexes(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        $this->warn('Rebuilding menu trgm indexes.');
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        DB::statement("CREATE INDEX IF NOT EXISTS menu_categories_translated_name_trgm_idx ON menu_categories USING gin ((lower(coalesce(translated_name->>'hy', '') || ' ' || coalesce(translated_name->>'ru', '') || ' ' || coalesce(translated_name->>'en', ''))) gin_trgm_ops)");
        DB::statement("CREATE INDEX IF NOT EXISTS menu_items_translated_name_trgm_idx ON menu_items USING gin ((lower(coalesce(translated_name->>'hy', '') || ' ' || coalesce(translated_name->>'ru', '') || ' ' || coalesce(translated_name->>'en', ''))) gin_trgm_ops)");
    }
}
