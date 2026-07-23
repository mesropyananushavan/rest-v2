<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Identity\Infrastructure\Models\Permission;
use App\Modules\Identity\Infrastructure\Models\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Identity\Infrastructure\Models\UserBranchAssignment;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;
use DateTimeInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use JsonException;
use PgSql\Connection as PgSqlConnection;
use RuntimeException;
use Throwable;

final class MenuSeedLoadCommand extends Command
{
    private const MODE_PRODUCTION_LIKE = 'production-like';

    private const MODE_GIANT_MENU = 'giant-menu';

    private const MIN_BATCH_SIZE = 5000;

    private const MAX_BATCH_SIZE = 20000;

    private const POSTGRES_BIND_PARAMETER_LIMIT = 60000;

    private const SEED_SOURCE_LOAD = 'load';

    private const LOAD_USER_PASSWORD = 'password';

    private const LOCAL_DATABASE_HOST = 'postgres';

    private const LOCK_TIMEOUT = '10s';

    /**
     * @var array<string, string>
     */
    private const LOAD_PERMISSIONS = [
        'identity.manage' => 'Manage users and roles',
        'menu.categories.manage' => 'Manage menu categories',
        'menu.items.manage' => 'Manage menu items',
        'orders.take' => 'Take orders',
        'payments.capture' => 'Capture payments',
    ];

    /**
     * @var list<string>
     */
    private const LOAD_MANAGER_PERMISSIONS = [
        'identity.manage',
        'menu.categories.manage',
        'menu.items.manage',
        'orders.take',
        'payments.capture',
    ];

    /**
     * @var array<string, list<string>>
     */
    private const COPY_COLUMNS = [
        'tenants' => [
            'name',
            'slug',
            'default_locale',
            'currency',
            'status',
            'seed_source',
            'created_at',
            'updated_at',
        ],
        'branches' => [
            'tenant_id',
            'name',
            'address',
            'phone',
            'locale',
            'timezone',
            'status',
            'created_at',
            'updated_at',
        ],
        'menu_categories' => [
            'tenant_id',
            'parent_id',
            'archived_with_category_id',
            'translated_name',
            'sort_order',
            'active',
            'deleted_at',
            'created_at',
            'updated_at',
        ],
        'menu_items' => [
            'tenant_id',
            'branch_id',
            'category_id',
            'translated_name',
            'translated_description',
            'internal_image',
            'public_image',
            'price_minor',
            'currency',
            'sort_order',
            'active',
            'archived_with_category_id',
            'deleted_at',
            'created_at',
            'updated_at',
        ],
    ];

    private ?PgSqlConnection $postgresCopyConnection = null;

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
        {--fresh : In production-like mode, recreate the local schema with baseline seed before loading; in other modes, delete previous load-generated tenants}
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
        $this->configurePostgresSessionTimeouts();

        $config = $this->config();
        $this->printPlan($config);

        $trgmDropped = false;
        $cleanupSeconds = null;
        $loadSeconds = null;
        $rebuildSeconds = null;

        try {
            if ($config['fresh']) {
                $startedAt = microtime(true);
                $this->deletePreviousLoadTenants($config);
                $cleanupSeconds = microtime(true) - $startedAt;
            }

            if ($config['dropRebuildTrgm']) {
                $this->dropTrgmIndexes();
                $trgmDropped = true;
            }

            $startedAt = microtime(true);
            match ($config['mode']) {
                self::MODE_PRODUCTION_LIKE => $this->seedProductionLike($config),
                self::MODE_GIANT_MENU => $this->seedGiantMenu($config),
            };
            $loadSeconds = microtime(true) - $startedAt;

            $this->verifyLoadedCounts($config);

            if ($trgmDropped) {
                $startedAt = microtime(true);
                $this->rebuildTrgmIndexes();
                $rebuildSeconds = microtime(true) - $startedAt;
            }

            $this->printPhaseTimings($cleanupSeconds, $loadSeconds, $rebuildSeconds);
        } catch (Throwable $exception) {
            if ($trgmDropped) {
                $this->warn('Load failed after dropping trgm indexes; rebuilding them before exiting.');
                $this->rebuildTrgmIndexes();
            }

            throw $exception;
        } finally {
            $this->closePostgresCopyConnection();
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
     *     fresh: bool,
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
            'fresh' => (bool) $this->option('fresh'),
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

    private function configurePostgresSessionTimeouts(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("SET lock_timeout = '".self::LOCK_TIMEOUT."'");
    }

    /**
     * @param  array{mode: 'production-like'|'giant-menu'}  $config
     */
    private function deletePreviousLoadTenants(array $config): void
    {
        if ($config['mode'] === self::MODE_PRODUCTION_LIKE) {
            $this->assertCanRecreateLocalSchema();

            if ($this->input->isInteractive() && ! $this->confirm('This will delete the entire local database, including demo tenants. Continue?', false)) {
                $this->fail('Fresh load cleanup cancelled before recreating the local schema.');
            }

            $this->info('Fresh load cleanup: recreating local schema with baseline seed.');

            $exitCode = $this->call('migrate:fresh', [
                '--seed' => true,
                '--force' => true,
            ]);

            if ($exitCode !== self::SUCCESS) {
                $this->fail('Fresh load cleanup failed while recreating the local schema.');
            }

            return;
        }

        $this->deletePreviousLoadTenantsByRows();
    }

    private function deletePreviousLoadTenantsByRows(): void
    {
        $loadTenantIds = DB::table('tenants')
            ->where('seed_source', self::SEED_SOURCE_LOAD)
            ->pluck('id')
            ->map(fn (mixed $tenantId): int => $this->integerDatabaseValue($tenantId, 'load tenant id'))
            ->all();

        if ($loadTenantIds === []) {
            $this->info('Fresh load cleanup: no previous load tenants found.');
            $this->assertNoLoadRowsRemain();

            return;
        }

        $deleted = 0;

        foreach (array_chunk($loadTenantIds, self::MAX_BATCH_SIZE) as $chunk) {
            $deleted += DB::transaction(static function () use ($chunk): int {
                // Self-referencing category FKs use restrict-on-delete, so remove
                // the generated tree bottom-up before deleting tenants.
                DB::table('menu_items')
                    ->whereIn('tenant_id', $chunk)
                    ->delete();
                DB::table('menu_categories')
                    ->whereIn('tenant_id', $chunk)
                    ->whereNotNull('parent_id')
                    ->delete();
                DB::table('menu_categories')
                    ->whereIn('tenant_id', $chunk)
                    ->delete();

                return DB::table('tenants')
                    ->whereIn('id', $chunk)
                    ->delete();
            });
        }

        $this->assertNoLoadRowsRemain();
        $this->info('Fresh load cleanup deleted tenants: '.number_format($deleted));
    }

    private function assertCanRecreateLocalSchema(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->fail('Refusing destructive --fresh schema recreation: application environment must be local or testing.');
        }

        $connectionName = config('database.default');

        if (! is_string($connectionName) || $connectionName === '') {
            $this->fail('Refusing destructive --fresh schema recreation: database.default must be a non-empty string.');
        }

        $connection = config("database.connections.{$connectionName}");

        if (! is_array($connection)) {
            $this->fail("Refusing destructive --fresh schema recreation: database connection [{$connectionName}] is not configured.");
        }

        $driver = $connection['driver'] ?? null;
        $host = $connection['host'] ?? null;
        $database = $connection['database'] ?? null;

        if (! is_string($driver) || $driver === '') {
            $this->fail("Refusing destructive --fresh schema recreation: connection [{$connectionName}] driver must be a non-empty string.");
        }

        if (! is_string($host) || $host === '') {
            $this->fail("Refusing destructive --fresh schema recreation: connection [{$connectionName}] host must be a non-empty string.");
        }

        if (! is_string($database) || $database === '') {
            $this->fail("Refusing destructive --fresh schema recreation: connection [{$connectionName}] database must be a non-empty string.");
        }

        if ($driver !== 'pgsql') {
            $this->fail("Refusing destructive --fresh schema recreation: expected local pgsql connection, got driver [{$driver}].");
        }

        if (! $this->isExpectedLocalDatabaseHost($host) || ! $this->isExpectedLocalDatabaseName($database)) {
            $this->fail(
                'Refusing destructive --fresh schema recreation: expected a local SmartRest database connection; '
                .'expected host ['.self::LOCAL_DATABASE_HOST."] and a local database name; got connection [{$connectionName}], host [{$host}], database [{$database}].",
            );
        }
    }

    private function isExpectedLocalDatabaseHost(string $host): bool
    {
        return $host === self::LOCAL_DATABASE_HOST;
    }

    private function isExpectedLocalDatabaseName(string $database): bool
    {
        return in_array($database, ['smartrest', 'smartrest_test', 'testing'], true);
    }

    private function assertNoLoadRowsRemain(): void
    {
        $remainingLoadTenants = DB::table('tenants')
            ->where('seed_source', self::SEED_SOURCE_LOAD)
            ->count();
        $remainingLoadCategories = DB::table('menu_categories')
            ->join('tenants', 'tenants.id', '=', 'menu_categories.tenant_id')
            ->where('tenants.seed_source', self::SEED_SOURCE_LOAD)
            ->count();
        $remainingLoadItems = DB::table('menu_items')
            ->join('tenants', 'tenants.id', '=', 'menu_items.tenant_id')
            ->where('tenants.seed_source', self::SEED_SOURCE_LOAD)
            ->count();
        $orphanedCategories = DB::table('menu_categories')
            ->leftJoin('tenants', 'tenants.id', '=', 'menu_categories.tenant_id')
            ->whereNull('tenants.id')
            ->count();
        $orphanedItems = DB::table('menu_items')
            ->leftJoin('tenants', 'tenants.id', '=', 'menu_items.tenant_id')
            ->whereNull('tenants.id')
            ->count();

        if (
            $remainingLoadTenants !== 0
            || $remainingLoadCategories !== 0
            || $remainingLoadItems !== 0
            || $orphanedCategories !== 0
            || $orphanedItems !== 0
        ) {
            $this->fail(sprintf(
                'Fresh load cleanup left load rows behind: tenants=%d, menu_categories=%d, menu_items=%d, orphaned_menu_categories=%d, orphaned_menu_items=%d.',
                $remainingLoadTenants,
                $remainingLoadCategories,
                $remainingLoadItems,
                $orphanedCategories,
                $orphanedItems,
            ));
        }
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
     *     fresh: bool,
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
        $this->line("load manager users: {$config['restaurants']}");
        $this->line("root_categories = restaurants * categories = {$config['restaurants']} * {$config['categories']} = ".number_format($rootCategories));
        $this->line('subcategories = root_categories * subcategories = '.number_format($rootCategories)." * {$config['subcategories']} = ".number_format($subcategories));
        $this->line('items = subcategories * items = '.number_format($subcategories)." * {$config['items']} = ".number_format($items));
        $this->line('total menu rows = '.number_format($rootCategories + $subcategories + $items));
        $this->line('batch size: '.number_format($config['batch']));
        $this->line('drop/rebuild trgm: '.($config['dropRebuildTrgm'] ? 'yes' : 'no'));
        $this->line('fresh load cleanup: '.($config['fresh'] ? ($config['mode'] === self::MODE_PRODUCTION_LIKE ? 'schema recreate' : 'delete load tenants') : 'no'));
        $this->line("sample login: {$this->loadManagerEmail($config['runId'], 1)} / ".self::LOAD_USER_PASSWORD);
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
        $this->insertLoadUsers($tenantIdsByRestaurant, $branchIdsByRestaurant, $config['runId']);

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
        $this->insertLoadUsers($tenantIdsByRestaurant, $branchIdsByRestaurant, $config['runId']);

        $rootIds = $this->insertRootCategories($tenantIdsByRestaurant, $config['categories'], $config['batch']);
        $subcategoryIds = $this->insertSubcategories($rootIds, $config['subcategories'], $config['batch']);

        $this->insertItems($subcategoryIds, $branchIdsByRestaurant, $config['items'], $config['batch']);
    }

    /**
     * @param  array{
     *     restaurants: int,
     *     categories: int,
     *     subcategories: int,
     *     items: int,
     *     runId: string,
     * }  $config
     */
    private function verifyLoadedCounts(array $config): void
    {
        $expectedRoots = $config['restaurants'] * $config['categories'];
        $expectedSubcategories = $expectedRoots * $config['subcategories'];
        $expectedCategories = $expectedRoots + $expectedSubcategories;
        $expectedItems = $expectedSubcategories * $config['items'];

        $loadTenantIds = DB::table('tenants')
            ->where('seed_source', self::SEED_SOURCE_LOAD)
            ->where('slug', 'like', "load-{$config['runId']}-%")
            ->pluck('id')
            ->map(fn (mixed $tenantId): int => $this->integerDatabaseValue($tenantId, 'loaded tenant id'))
            ->all();

        $actualTenants = count($loadTenantIds);
        $actualRoots = $loadTenantIds === []
            ? 0
            : DB::table('menu_categories')->whereIn('tenant_id', $loadTenantIds)->whereNull('parent_id')->count();
        $actualSubcategories = $loadTenantIds === []
            ? 0
            : DB::table('menu_categories')->whereIn('tenant_id', $loadTenantIds)->whereNotNull('parent_id')->count();
        $actualCategories = $actualRoots + $actualSubcategories;
        $actualItems = $loadTenantIds === []
            ? 0
            : DB::table('menu_items')->whereIn('tenant_id', $loadTenantIds)->count();

        if (
            $actualTenants !== $config['restaurants']
            || $actualRoots !== $expectedRoots
            || $actualSubcategories !== $expectedSubcategories
            || $actualCategories !== $expectedCategories
            || $actualItems !== $expectedItems
        ) {
            $this->fail(sprintf(
                'menu:seed-load count verification failed: tenants expected=%d actual=%d, roots expected=%d actual=%d, subcategories expected=%d actual=%d, menu_categories expected=%d actual=%d, menu_items expected=%d actual=%d.',
                $config['restaurants'],
                $actualTenants,
                $expectedRoots,
                $actualRoots,
                $expectedSubcategories,
                $actualSubcategories,
                $expectedCategories,
                $actualCategories,
                $expectedItems,
                $actualItems,
            ));
        }

        $this->info(sprintf(
            'Verified load counts: tenants=%d, roots=%d, subcategories=%d, menu_categories=%d, menu_items=%d.',
            $actualTenants,
            $actualRoots,
            $actualSubcategories,
            $actualCategories,
            $actualItems,
        ));
    }

    private function printPhaseTimings(?float $cleanupSeconds, ?float $loadSeconds, ?float $rebuildSeconds): void
    {
        $this->line('menu:seed-load phase timings');
        $this->line('cleanup_seconds='.$this->formattedPhaseSeconds($cleanupSeconds));
        $this->line('copy_load_seconds='.$this->formattedPhaseSeconds($loadSeconds));
        $this->line('trgm_rebuild_seconds='.$this->formattedPhaseSeconds($rebuildSeconds));
    }

    private function formattedPhaseSeconds(?float $seconds): string
    {
        return $seconds === null
            ? 'skipped'
            : number_format($seconds, 3, '.', '');
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
                'seed_source' => self::SEED_SOURCE_LOAD,
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
     * @param  array<int, int>  $branchIdsByRestaurant
     */
    private function insertLoadUsers(array $tenantIdsByRestaurant, array $branchIdsByRestaurant, string $runId): void
    {
        $userIdsByRestaurant = [];

        try {
            foreach ($tenantIdsByRestaurant as $restaurant => $tenantId) {
                app(TenantResolver::class)->set($tenantId);

                $permissions = $this->createLoadPermissions();
                $role = $this->createLoadManagerRole($permissions);
                $user = $this->createLoadManagerUser($role, $runId, $restaurant);

                UserBranchAssignment::query()->create([
                    'user_id' => (int) $user->id,
                    'branch_id' => $branchIdsByRestaurant[$restaurant],
                ]);

                $userIdsByRestaurant[$restaurant] = (int) $user->id;
            }
        } finally {
            app(BranchContext::class)->clear();
            app(TenantResolver::class)->clear();
        }

        $this->verifyLoadUsers($tenantIdsByRestaurant, $branchIdsByRestaurant, $userIdsByRestaurant);

        $this->info('Inserted load manager users: '.number_format(count($userIdsByRestaurant)));
    }

    /**
     * @return array<string, Permission>
     */
    private function createLoadPermissions(): array
    {
        $permissions = [];

        foreach (self::LOAD_PERMISSIONS as $code => $name) {
            $permission = Permission::query()->create([
                'code' => $code,
                'name' => $name,
            ]);

            $permissions[$code] = $permission;
        }

        return $permissions;
    }

    /**
     * @param  array<string, Permission>  $permissions
     */
    private function createLoadManagerRole(array $permissions): Role
    {
        $role = Role::query()->create([
            'code' => 'manager',
            'name' => 'Manager',
        ]);

        $role->permissions()->syncWithPivotValues(
            collect(self::LOAD_MANAGER_PERMISSIONS)
                ->map(fn (string $code): int => (int) $permissions[$code]->id)
                ->all(),
            ['tenant_id' => (int) $role->tenant_id],
        );

        return $role;
    }

    private function createLoadManagerUser(Role $role, string $runId, int $restaurant): User
    {
        return User::query()->create([
            'role_id' => (int) $role->id,
            'name' => "Load Manager {$runId} #{$restaurant}",
            'email' => $this->loadManagerEmail($runId, $restaurant),
            'username' => $this->loadManagerUsername($runId, $restaurant),
            'default_locale' => 'hy',
            'active' => true,
            'email_verified_at' => now(),
            'password' => self::LOAD_USER_PASSWORD,
            'is_superadmin' => false,
        ]);
    }

    /**
     * @param  array<int, int>  $tenantIdsByRestaurant
     * @param  array<int, int>  $branchIdsByRestaurant
     * @param  array<int, int>  $userIdsByRestaurant
     */
    private function verifyLoadUsers(array $tenantIdsByRestaurant, array $branchIdsByRestaurant, array $userIdsByRestaurant): void
    {
        $activeUsers = DB::table('users')
            ->whereIn('id', array_values($userIdsByRestaurant))
            ->whereIn('tenant_id', array_values($tenantIdsByRestaurant))
            ->where('active', true)
            ->count();

        if ($activeUsers !== count($tenantIdsByRestaurant)) {
            $this->fail('Load user verification failed: active user count mismatch.');
        }

        $branchAssignments = DB::table('user_branch_assignments')
            ->whereIn('tenant_id', array_values($tenantIdsByRestaurant))
            ->whereIn('user_id', array_values($userIdsByRestaurant))
            ->whereIn('branch_id', array_values($branchIdsByRestaurant))
            ->count();

        if ($branchAssignments !== count($tenantIdsByRestaurant)) {
            $this->fail('Load user verification failed: branch assignment count mismatch.');
        }

        $managerPermissions = DB::table('role_permissions')
            ->join('roles', 'roles.id', '=', 'role_permissions.role_id')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->whereIn('role_permissions.tenant_id', array_values($tenantIdsByRestaurant))
            ->where('roles.code', 'manager')
            ->whereIn('permissions.code', self::LOAD_MANAGER_PERMISSIONS)
            ->count();

        if ($managerPermissions !== count($tenantIdsByRestaurant) * count(self::LOAD_MANAGER_PERMISSIONS)) {
            $this->fail('Load user verification failed: manager permission count mismatch.');
        }
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

        if ($rows === []) {
            return;
        }

        /** @var int<1, max> $positiveBatch */
        $positiveBatch = $batch;

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            $this->copyRowsInBatches($table, $rows, $positiveBatch);

            return;
        }

        $safeInsertBatch = $this->safeInsertBatchSize($rows, $positiveBatch);

        foreach (array_chunk($rows, $safeInsertBatch) as $chunk) {
            DB::transaction(static function () use ($table, $chunk): void {
                DB::table($table)->insert($chunk);
            });
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  int<1, max>  $batch
     */
    private function copyRowsInBatches(string $table, array $rows, int $batch): void
    {
        $columns = self::COPY_COLUMNS[$table] ?? null;

        if ($columns === null) {
            throw new RuntimeException("Unsupported COPY table [{$table}].");
        }

        $connection = $this->postgresCopyConnection();

        foreach (array_chunk($rows, $batch) as $chunk) {
            $this->copyRowsChunk($connection, $table, $columns, $chunk);
        }
    }

    /**
     * @param  list<string>  $columns
     * @param  list<array<string, mixed>>  $rows
     */
    private function copyRowsChunk(PgSqlConnection $connection, string $table, array $columns, array $rows): void
    {
        $copySql = sprintf(
            'COPY %s (%s) FROM STDIN WITH (FORMAT csv, NULL %s)',
            $this->postgresIdentifier($table),
            implode(', ', array_map(fn (string $column): string => $this->postgresIdentifier($column), $columns)),
            "'\\N'",
        );

        $this->postgresQuery($connection, 'BEGIN');

        try {
            $this->postgresQuery($connection, $copySql);

            foreach ($rows as $row) {
                $this->postgresPutLine($connection, $this->csvLine($row, $columns));
            }

            $this->postgresEndCopy($connection);
            $this->postgresQuery($connection, 'COMMIT');
        } catch (Throwable $exception) {
            $this->closePostgresCopyConnection();

            throw $exception;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  int<1, max>  $requestedBatch
     * @return int<1, max>
     */
    private function safeInsertBatchSize(array $rows, int $requestedBatch): int
    {
        $columnCount = count($rows[0]);

        if ($columnCount < 1) {
            throw new RuntimeException('Cannot insert rows without columns.');
        }

        $safeBatch = max(1, intdiv(self::POSTGRES_BIND_PARAMETER_LIMIT, $columnCount));

        /** @var int<1, max> $boundedBatch */
        $boundedBatch = min($requestedBatch, $safeBatch);

        return $boundedBatch;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $columns
     */
    private function csvLine(array $row, array $columns): string
    {
        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            throw new RuntimeException('Unable to open temporary CSV stream.');
        }

        $values = [];

        foreach ($columns as $column) {
            $values[] = $this->csvValue($row[$column] ?? null);
        }

        if (fputcsv($stream, $values, ',', '"', '') === false) {
            fclose($stream);

            throw new RuntimeException('Unable to write CSV row.');
        }

        rewind($stream);
        $line = stream_get_contents($stream);
        fclose($stream);

        if ($line === false) {
            throw new RuntimeException('Unable to read CSV row.');
        }

        return $line;
    }

    private function csvValue(mixed $value): string
    {
        if ($value === null) {
            return '\N';
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value) || is_string($value)) {
            return (string) $value;
        }

        throw new RuntimeException('Unsupported COPY value type.');
    }

    private function postgresCopyConnection(): PgSqlConnection
    {
        if ($this->postgresCopyConnection !== null) {
            return $this->postgresCopyConnection;
        }

        $this->requirePostgresCopyFunctions();

        $connection = pg_connect($this->postgresConnectionString(), $this->postgresForceNewConnectionFlag());

        if ($connection === false) {
            throw new RuntimeException('Unable to open PostgreSQL COPY connection.');
        }

        $copyConnection = $connection;
        $this->postgresCopyConnection = $copyConnection;
        $this->postgresQuery($copyConnection, "SET lock_timeout = '".self::LOCK_TIMEOUT."'");

        return $copyConnection;
    }

    private function requirePostgresCopyFunctions(): void
    {
        foreach (['pg_connect', 'pg_query', 'pg_put_line', 'pg_end_copy', 'pg_last_error', 'pg_close'] as $function) {
            if (! function_exists($function)) {
                throw new RuntimeException(
                    'PostgreSQL COPY requires the PHP pgsql extension. Rebuild the php-fpm image so docker/php/Dockerfile installs ext-pgsql.',
                );
            }
        }
    }

    private function postgresForceNewConnectionFlag(): int
    {
        return defined('PGSQL_CONNECT_FORCE_NEW') ? (int) constant('PGSQL_CONNECT_FORCE_NEW') : 0;
    }

    private function postgresConnectionString(): string
    {
        $config = config('database.connections.pgsql');

        if (! is_array($config)) {
            throw new RuntimeException('PostgreSQL database configuration is missing.');
        }

        $parts = [];

        foreach ([
            'host' => $config['host'] ?? null,
            'port' => $config['port'] ?? null,
            'dbname' => $config['database'] ?? null,
            'user' => $config['username'] ?? null,
            'password' => $config['password'] ?? null,
        ] as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            if (! is_scalar($value)) {
                throw new RuntimeException("Invalid PostgreSQL connection value [{$key}].");
            }

            $parts[] = $key."='".$this->postgresConnectionValue((string) $value)."'";
        }

        return implode(' ', $parts);
    }

    private function postgresConnectionValue(string $value): string
    {
        return str_replace(['\\', "'"], ['\\\\', "\\'"], $value);
    }

    private function postgresIdentifier(string $identifier): string
    {
        if (preg_match('/^[a-z_][a-z0-9_]*$/', $identifier) !== 1) {
            throw new RuntimeException("Invalid PostgreSQL identifier [{$identifier}].");
        }

        return '"'.$identifier.'"';
    }

    private function postgresQuery(PgSqlConnection $connection, string $sql): void
    {
        $result = pg_query($connection, $sql);

        if ($result === false) {
            throw new RuntimeException($this->postgresLastError($connection));
        }
    }

    private function postgresPutLine(PgSqlConnection $connection, string $line): void
    {
        if (pg_put_line($connection, $line) === false) {
            throw new RuntimeException($this->postgresLastError($connection));
        }
    }

    private function postgresEndCopy(PgSqlConnection $connection): void
    {
        if (pg_end_copy($connection) === false) {
            throw new RuntimeException($this->postgresLastError($connection));
        }
    }

    private function postgresLastError(PgSqlConnection $connection): string
    {
        $message = pg_last_error($connection);

        return $message !== ''
            ? $message
            : 'Unknown PostgreSQL COPY error.';
    }

    private function closePostgresCopyConnection(): void
    {
        if ($this->postgresCopyConnection === null || ! function_exists('pg_close')) {
            $this->postgresCopyConnection = null;

            return;
        }

        pg_close($this->postgresCopyConnection);
        $this->postgresCopyConnection = null;
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

    private function loadManagerEmail(string $runId, int $restaurant): string
    {
        return "load-manager+{$runId}-restaurant-{$restaurant}@smartrest.test";
    }

    private function loadManagerUsername(string $runId, int $restaurant): string
    {
        return "load-manager-{$runId}-restaurant-{$restaurant}";
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
