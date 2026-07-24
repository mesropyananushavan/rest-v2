<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Money\Money;
use App\Support\Money\MoneyFormatter;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use JsonException;
use RuntimeException;

final class MenuContextSmokeCommand extends Command
{
    private const PER_PAGE = 25;

    protected $signature = 'smoke:menu-context
        {--base-url=http://nginx : Base URL reachable from the PHP container}
        {--email=manager@arat.test : Demo operator email}
        {--password=password : Demo operator password}';

    protected $description = 'Run the Menu context preservation HTTP smoke through real session login and CSRF.';

    private CookieJar $cookies;

    /**
     * @var list<array{mode: string, step: string, status: int, marker: string, absent: string, parameter: string}>
     */
    private array $rows = [];

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('Refusing to run outside local/testing environments.');

            return self::FAILURE;
        }

        $this->cookies = new CookieJar;
        $baseUrl = rtrim((string) $this->option('base-url'), '/');

        if ($baseUrl === '') {
            $this->error('--base-url must be a non-empty URL.');

            return self::FAILURE;
        }

        try {
            $data = $this->smokeData();
            $this->line(sprintf(
                'category_mode category_id=%d active_items=%d page_parameter=item_page',
                $data['category']['category_id'],
                $data['category']['active_items'],
            ));
            $this->line(sprintf(
                'search_mode term="%s" active_results=%d page_parameter=search_page',
                $data['search']['term'],
                $data['search']['active_results'],
            ));
            $this->login($baseUrl);
            $this->runCategoryMode($baseUrl, $data['category']);
            $this->runSearchMode($baseUrl, $data['search']);
        } catch (RuntimeException|JsonException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(['Mode', 'Step', 'Status', 'Marker', 'Absent marker', 'Page parameter'], $this->rows);
        $this->info('Menu context HTTP smoke complete.');

        return self::SUCCESS;
    }

    private function login(string $baseUrl): void
    {
        $loginForm = $this->request()
            ->get($this->url($baseUrl, '/login'));

        $this->assertStatus($loginForm, 200, 'login form');
        $this->record('AUTH', 'GET /login', $loginForm->status(), 'login form loaded');

        $login = $this->request()
            ->asForm()
            ->post($this->url($baseUrl, '/login'), [
                '_token' => $this->csrfToken($loginForm->body(), 'login form'),
                'email' => (string) $this->option('email'),
                'password' => (string) $this->option('password'),
            ]);

        $this->assertStatus($login, 302, 'login submit');
        $this->record('AUTH', 'POST /login', $login->status(), (string) $this->option('email'));
    }

    /**
     * @param  array{category_id: int, active_items: int, page1: array{id: int, marker: string}, page2: array{id: int, marker: string}}  $data
     */
    private function runCategoryMode(string $baseUrl, array $data): void
    {
        $context = [
            'category' => $data['category_id'],
            'item_page' => 2,
        ];
        $page1Url = $this->url($baseUrl, '/admin/menu', ['category' => $data['category_id']]);
        $page2Url = $this->url($baseUrl, '/admin/menu', $context);

        $page1 = $this->request()->get($page1Url);
        $this->assertStatus($page1, 200, 'category page 1');
        $this->assertContains($page1->body(), $data['page1']['marker'], 'category page 1 marker');
        $this->assertNotContains($page1->body(), $data['page2']['marker'], 'category page 1 excludes page 2 marker');
        $this->record('CATEGORY', 'open page 1', $page1->status(), $data['page1']['marker'], $data['page2']['marker'], 'item_page');

        $page2 = $this->request()->get($page2Url);
        $this->assertStatus($page2, 200, 'category page 2');
        $this->assertContains($page2->body(), $data['page2']['marker'], 'category page 2 marker');
        $this->assertNotContains($page2->body(), $data['page1']['marker'], 'category page 2 excludes page 1 marker');
        $this->record('CATEGORY', 'open page 2', $page2->status(), $data['page2']['marker'], $data['page1']['marker'], 'item_page');

        $editUrl = $this->url($baseUrl, "/admin/menu/items/{$data['page2']['id']}/edit", [
            'context' => $context,
        ]);
        $edit = $this->request()->get($editUrl);
        $this->assertStatus($edit, 200, 'category edit page');
        $this->record('CATEGORY', 'GET edit page', $edit->status(), $data['page2']['marker'], '', 'item_page');

        $save = $this->saveItem($baseUrl, $edit, $data['page2']['id'], $context);
        $landing = $this->request()->get($this->normalizeLocation($baseUrl, $save));
        $this->assertStatus($landing, 200, 'category save landing');
        $this->assertContains($landing->body(), $data['page2']['marker'], 'category save landing page 2 marker');
        $this->assertNotContains($landing->body(), $data['page1']['marker'], 'category save landing excludes page 1 marker');
        $this->record('CATEGORY', 'save landing', $landing->status(), $data['page2']['marker'], $data['page1']['marker'], 'item_page');

        $cancelEdit = $this->request()->get($editUrl);
        $this->assertStatus($cancelEdit, 200, 'category cancel edit page');
        $this->assertContains($cancelEdit->body(), $this->escapedHtml($this->pathAndQuery($page2Url)), 'category cancel link');

        $cancelLanding = $this->request()->get($page2Url);
        $this->assertStatus($cancelLanding, 200, 'category cancel landing');
        $this->assertContains($cancelLanding->body(), $data['page2']['marker'], 'category cancel landing page 2 marker');
        $this->assertNotContains($cancelLanding->body(), $data['page1']['marker'], 'category cancel landing excludes page 1 marker');
        $this->record('CATEGORY', 'cancel landing', $cancelLanding->status(), $data['page2']['marker'], $data['page1']['marker'], 'item_page');
    }

    /**
     * @param  array{term: string, active_results: int, page1: array{id: int, marker: string}, page2: array{id: int, marker: string}}  $data
     */
    private function runSearchMode(string $baseUrl, array $data): void
    {
        $context = [
            'q' => $data['term'],
            'search_page' => 2,
        ];
        $page2Url = $this->url($baseUrl, '/admin/menu', $context);

        $page2 = $this->request()->get($page2Url);
        $this->assertStatus($page2, 200, 'search page 2');
        $this->assertContains($page2->body(), $data['page2']['marker'], 'search page 2 marker');
        $this->assertNotContains($page2->body(), $data['page1']['marker'], 'search page 2 excludes page 1 marker');
        $this->record('SEARCH', 'open search page 2', $page2->status(), $data['page2']['marker'], $data['page1']['marker'], 'search_page');

        $editUrl = $this->url($baseUrl, "/admin/menu/items/{$data['page2']['id']}/edit", [
            'context' => $context,
        ]);
        $edit = $this->request()->get($editUrl);
        $this->assertStatus($edit, 200, 'search edit page');
        $this->record('SEARCH', 'GET edit page', $edit->status(), $data['page2']['marker'], '', 'search_page');

        $save = $this->saveItem($baseUrl, $edit, $data['page2']['id'], $context);
        $landing = $this->request()->get($this->normalizeLocation($baseUrl, $save));
        $this->assertStatus($landing, 200, 'search save landing');
        $this->assertContains($landing->body(), $data['page2']['marker'], 'search save landing marker');
        $this->assertNotContains($landing->body(), $data['page1']['marker'], 'search save landing excludes reset marker');
        $this->record('SEARCH', 'save landing', $landing->status(), $data['page2']['marker'], $data['page1']['marker'], 'search_page');
    }

    /**
     * @param  array<string, int|string>  $context
     */
    private function saveItem(string $baseUrl, Response $edit, int $itemId, array $context): Response
    {
        $item = $this->itemPayload($itemId);
        $response = $this->request()
            ->asForm()
            ->post($this->url($baseUrl, "/admin/menu/items/{$itemId}"), [
                '_token' => $this->csrfToken($edit->body(), "item {$itemId} edit form"),
                '_method' => 'PUT',
                'context' => $context,
            ] + $item);

        $this->assertStatus($response, 302, "item {$itemId} save");

        return $response;
    }

    /**
     * @return array{
     *     category: array{category_id: int, active_items: int, page1: array{id: int, marker: string}, page2: array{id: int, marker: string}},
     *     search: array{term: string, active_results: int, page1: array{id: int, marker: string}, page2: array{id: int, marker: string}}
     * }
     *
     * @throws JsonException
     */
    private function smokeData(): array
    {
        $tenantId = $this->intValue(DB::table('tenants')->where('slug', 'arat-riverside')->value('id'), 'Arat tenant id');
        $branchId = $this->intValue(DB::table('branches')->where('tenant_id', $tenantId)->where('name', 'Arat Kentron')->value('id'), 'Arat Kentron branch id');

        $categoryId = $this->intValue(DB::table('menu_items')
            ->where('tenant_id', $tenantId)
            ->where('branch_id', $branchId)
            ->where('active', true)
            ->whereNotNull('load_test_key')
            ->whereNull('deleted_at')
            ->groupBy('category_id')
            ->havingRaw('count(*) > ?', [self::PER_PAGE])
            ->orderBy('category_id')
            ->value('category_id'), 'multi-page category id');

        $categoryItems = $this->orderedItems([
            ['category_id', '=', $categoryId],
        ], $tenantId, $branchId, self::PER_PAGE + 1);

        $searchTerm = 'arat-riverside 1-';
        $searchItems = $this->orderedItems([
            ['translated_name', 'like', $searchTerm],
        ], $tenantId, $branchId, self::PER_PAGE + 1);

        return [
            'category' => [
                'category_id' => $categoryId,
                'active_items' => $this->matchingCount([
                    ['category_id', '=', $categoryId],
                ], $tenantId, $branchId),
                'page1' => $categoryItems[0],
                'page2' => $categoryItems[self::PER_PAGE],
            ],
            'search' => [
                'term' => $searchTerm,
                'active_results' => $this->matchingCount([
                    ['translated_name', 'like', $searchTerm],
                ], $tenantId, $branchId),
                'page1' => $searchItems[0],
                'page2' => $searchItems[self::PER_PAGE],
            ],
        ];
    }

    /**
     * @param  list<array{0: string, 1: string, 2: int|string}>  $filters
     */
    private function matchingCount(array $filters, int $tenantId, int $branchId): int
    {
        return $this->filteredItemQuery($filters, $tenantId, $branchId)->count();
    }

    /**
     * @param  list<array{0: string, 1: string, 2: int|string}>  $filters
     * @return list<array{id: int, marker: string}>
     *
     * @throws JsonException
     */
    private function orderedItems(array $filters, int $tenantId, int $branchId, int $needed): array
    {
        $query = $this->filteredItemQuery($filters, $tenantId, $branchId);

        $items = $query
            ->orderBy('sort_order')
            ->orderBy('id')
            ->limit($needed)
            ->get(['id', 'translated_name']);

        if ($items->count() < $needed) {
            throw new RuntimeException("menu:load-test-data must provide at least {$needed} active matching items; found {$items->count()}.");
        }

        return array_values($items
            ->map(fn (object $item): array => [
                'id' => $this->intValue($item->id, 'item id'),
                'marker' => $this->localizedMarker($item->translated_name),
            ])
            ->all());
    }

    /**
     * @param  list<array{0: string, 1: string, 2: int|string}>  $filters
     */
    private function filteredItemQuery(array $filters, int $tenantId, int $branchId): Builder
    {
        $query = DB::table('menu_items')
            ->where('tenant_id', $tenantId)
            ->where('branch_id', $branchId)
            ->where('active', true)
            ->whereNotNull('load_test_key')
            ->whereNull('deleted_at');

        foreach ($filters as [$column, $operator, $value]) {
            if ($column === 'translated_name' && $operator === 'like') {
                $query->whereRaw("lower(coalesce(translated_name->>'hy', '') || ' ' || coalesce(translated_name->>'ru', '') || ' ' || coalesce(translated_name->>'en', '')) like ?", ['%'.strtolower((string) $value).'%']);

                continue;
            }

            $query->where($column, $operator, $value);
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    private function itemPayload(int $itemId): array
    {
        $item = DB::table('menu_items')->where('id', $itemId)->first([
            'category_id',
            'translated_name',
            'translated_description',
            'price_minor',
            'currency',
            'sort_order',
            'active',
        ]);

        if ($item === null) {
            throw new RuntimeException("Menu item {$itemId} was not found.");
        }

        $name = $this->localizedArray($item->translated_name);
        $description = $item->translated_description === null ? [] : $this->localizedArray($item->translated_description);
        if (! is_string($item->currency)) {
            throw new RuntimeException('Menu item currency must be a string.');
        }

        $currency = $item->currency;

        return [
            'category_id' => $this->intValue($item->category_id, 'item category id'),
            'name_hy' => $name['hy'] ?? '',
            'name_ru' => $name['ru'] ?? '',
            'name_en' => $name['en'] ?? '',
            'description_hy' => $description['hy'] ?? '',
            'description_ru' => $description['ru'] ?? '',
            'description_en' => $description['en'] ?? '',
            'price_major' => MoneyFormatter::toMajor(new Money($this->intValue($item->price_minor, 'item price'), $currency)),
            'currency' => $currency,
            'sort_order' => $this->intValue($item->sort_order, 'item sort order'),
            'active' => $item->active ? '1' : '0',
        ];
    }

    private function request(): PendingRequest
    {
        return Http::accept('text/html')
            ->withOptions([
                'allow_redirects' => false,
                'cookies' => $this->cookies,
                'http_errors' => false,
            ]);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function url(string $baseUrl, string $path, array $query = []): string
    {
        $url = $baseUrl.$path;

        return $query === [] ? $url : $url.'?'.http_build_query($query);
    }

    private function normalizeLocation(string $baseUrl, Response $response): string
    {
        $location = $response->header('Location');

        if ($location === '') {
            throw new RuntimeException('Redirect response did not include a Location header.');
        }

        if (str_starts_with($location, '/')) {
            return $baseUrl.$location;
        }

        $path = parse_url($location, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            throw new RuntimeException("Redirect Location [{$location}] did not contain a path.");
        }

        $query = parse_url($location, PHP_URL_QUERY);

        return $baseUrl.$path.(is_string($query) && $query !== '' ? '?'.$query : '');
    }

    private function pathAndQuery(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            throw new RuntimeException("URL [{$url}] did not contain a path.");
        }

        $query = parse_url($url, PHP_URL_QUERY);

        return $path.(is_string($query) && $query !== '' ? '?'.$query : '');
    }

    private function escapedHtml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function csrfToken(string $html, string $source): string
    {
        if (preg_match('/name="_token"[^>]*value="([^"]+)"/', $html, $matches) !== 1) {
            throw new RuntimeException("Unable to find CSRF token in {$source}.");
        }

        return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function assertStatus(Response $response, int $expected, string $step): void
    {
        if ($response->status() !== $expected) {
            throw new RuntimeException("Expected {$step} to return HTTP {$expected}; got {$response->status()}.");
        }
    }

    private function assertContains(string $html, string $marker, string $step): void
    {
        if (! str_contains($html, $marker)) {
            throw new RuntimeException("Expected {$step} to contain marker [{$marker}].");
        }
    }

    private function assertNotContains(string $html, string $marker, string $step): void
    {
        if ($marker !== '' && str_contains($html, $marker)) {
            throw new RuntimeException("Expected {$step} not to contain marker [{$marker}].");
        }
    }

    private function record(string $mode, string $step, int $status, string $marker, string $absent = '', string $parameter = ''): void
    {
        $this->rows[] = [
            'mode' => $mode,
            'step' => $step,
            'status' => $status,
            'marker' => $marker,
            'absent' => $absent,
            'parameter' => $parameter,
        ];
    }

    /**
     * @throws JsonException
     */
    private function localizedMarker(mixed $json): string
    {
        $name = $this->localizedArray($json);

        return (string) ($name['hy'] ?? $name['en'] ?? $name['ru'] ?? '');
    }

    /**
     * @return array<string, string>
     *
     * @throws JsonException
     */
    private function localizedArray(mixed $json): array
    {
        if (! is_string($json)) {
            throw new RuntimeException('Expected localized JSON value to be a string.');
        }

        /** @var array<string, string> $decoded */
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }

    private function intValue(mixed $value, string $label): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        throw new RuntimeException("Unable to resolve {$label}.");
    }
}
