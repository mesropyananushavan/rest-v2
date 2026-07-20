<?php

declare(strict_types=1);

namespace App\Modules\Menu\Infrastructure\Seeders;

use App\Modules\Menu\Application\RemoveMenuItemImage;
use App\Modules\Menu\Application\ReplaceMenuItemImage;
use App\Modules\Menu\Domain\MenuItemImageSlot;
use App\Modules\Menu\Infrastructure\Models\MenuCategory;
use App\Modules\Menu\Infrastructure\Models\MenuItem;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use RuntimeException;

final class MenuDemoSeeder extends Seeder
{
    /**
     * @param  array{tenants: array<string, int>, branches: array<string, int>}  $demo
     */
    public function seed(array $demo): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Demo seeders must run only in local or testing environments.');
        }

        $tenantResolver = app(TenantResolver::class);
        $branchContext = app(BranchContext::class);

        foreach ($this->tenantMenus() as $tenantSlug => $tenantMenu) {
            $tenantId = $demo['tenants'][$tenantSlug];
            $tenantResolver->set($tenantId);

            $categories = [];

            foreach ($tenantMenu['categories'] as $categoryRow) {
                $category = MenuCategory::query()->updateOrCreate(
                    [
                        'tenant_id' => $tenantId,
                        'sort_order' => $categoryRow['sort_order'],
                    ],
                    [
                        'translated_name' => $categoryRow['name'],
                        'active' => true,
                    ],
                );

                $categories[$categoryRow['key']] = $category;
            }

            foreach ($tenantMenu['branches'] as $branchKey => $items) {
                $branchId = $demo['branches'][$branchKey];
                $branchContext->set($branchId);

                foreach ($items as $itemRow) {
                    $category = $categories[$itemRow['category']];

                    $item = MenuItem::query()->updateOrCreate(
                        [
                            'tenant_id' => $tenantId,
                            'branch_id' => $branchId,
                            'sort_order' => $itemRow['sort_order'],
                        ],
                        [
                            'category_id' => (int) $category->id,
                            'translated_name' => $itemRow['name'],
                            'translated_description' => $itemRow['description'],
                            'price_minor' => $itemRow['price_minor'],
                            'currency' => $tenantMenu['currency'],
                            'active' => true,
                        ],
                    );

                    $this->syncItemImages($item, $itemRow);
                }
            }
        }

        $branchContext->clear();
        $tenantResolver->clear();
    }

    /**
     * @return array<string, array{
     *     currency: string,
     *     categories: list<array{key: string, sort_order: int, name: array{hy: string, ru: string, en: string}}>,
     *     branches: array<string, list<array{
     *         category: string,
     *         sort_order: int,
     *         name: array{hy: string, ru: string, en: string},
     *         description: array{hy: string, ru: string, en: string},
     *         price_minor: int,
     *         internal_image_fixture?: string,
     *         public_image_fixture?: string
     *     }>>
     * }>
     */
    private function tenantMenus(): array
    {
        return [
            'arat-riverside' => [
                'currency' => 'AMD',
                'categories' => [
                    ['key' => 'breakfast', 'sort_order' => 10, 'name' => $this->localized('Նախաճաշ', 'Завтраки', 'Breakfast')],
                    ['key' => 'salads', 'sort_order' => 20, 'name' => $this->localized('Աղցաններ', 'Салаты', 'Salads')],
                    ['key' => 'mains', 'sort_order' => 30, 'name' => $this->localized('Հիմնական ուտեստներ', 'Основные блюда', 'Main dishes')],
                ],
                'branches' => [
                    'arat-kentron' => [
                        ['category' => 'breakfast', 'sort_order' => 10, 'name' => $this->localized('Լոռի ձվածեղ', 'Омлет Лори', 'Lori omelette'), 'description' => $this->localized('Ձու, լոռի պանիր, կանաչի', 'Яйца, сыр лори, зелень', 'Eggs, Lori cheese, greens'), 'price_minor' => 220000, 'internal_image_fixture' => 'staff-omelette.png'],
                        ['category' => 'salads', 'sort_order' => 20, 'name' => $this->localized('Երեւանյան աղցան', 'Ереванский салат', 'Yerevan salad'), 'description' => $this->localized('Թարմ բանջարեղեն եւ ռեհան', 'Свежие овощи и базилик', 'Fresh vegetables and basil'), 'price_minor' => 260000, 'public_image_fixture' => 'guest-salad.png'],
                        ['category' => 'mains', 'sort_order' => 30, 'name' => $this->localized('Խորոված հավ', 'Куриный хоровац', 'Chicken khorovats'), 'description' => $this->localized('Ածուխի վրա պատրաստված հավ', 'Курица на углях', 'Charcoal-grilled chicken'), 'price_minor' => 380000],
                    ],
                    'arat-dilijan' => [
                        ['category' => 'breakfast', 'sort_order' => 10, 'name' => $this->localized('Դիլիջանյան նախաճաշ', 'Дилижанский завтрак', 'Dilijan breakfast'), 'description' => $this->localized('Մածուն, մեղր, հաց', 'Мацун, мёд, хлеб', 'Matzoon, honey, bread'), 'price_minor' => 240000],
                        ['category' => 'salads', 'sort_order' => 20, 'name' => $this->localized('Անտառային աղցան', 'Лесной салат', 'Forest salad'), 'description' => $this->localized('Կանաչի, ընկույզ, սունկ', 'Зелень, орехи, грибы', 'Greens, walnuts, mushrooms'), 'price_minor' => 280000],
                    ],
                ],
            ],
            'northstar-bistro' => [
                'currency' => 'USD',
                'categories' => [
                    ['key' => 'starters', 'sort_order' => 10, 'name' => $this->localized('Նախուտեստներ', 'Закуски', 'Starters')],
                    ['key' => 'burgers', 'sort_order' => 20, 'name' => $this->localized('Բուրգերներ', 'Бургеры', 'Burgers')],
                ],
                'branches' => [
                    'northstar-downtown' => [
                        ['category' => 'starters', 'sort_order' => 10, 'name' => $this->localized('Կորն չաուդեր', 'Кукурузный чаудер', 'Corn chowder'), 'description' => $this->localized('Կրեմային ապուր եգիպտացորենով', 'Кремовый суп с кукурузой', 'Cream soup with corn'), 'price_minor' => 799],
                        ['category' => 'burgers', 'sort_order' => 20, 'name' => $this->localized('Northstar բուրգեր', 'Бургер Northstar', 'Northstar burger'), 'description' => $this->localized('Տավարի միս, չեդդեր, թթու վարունգ', 'Говядина, чеддер, маринованные огурцы', 'Beef, cheddar, pickles'), 'price_minor' => 1499],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array{hy: string, ru: string, en: string}
     */
    private function localized(string $hy, string $ru, string $en): array
    {
        return [
            'hy' => $hy,
            'ru' => $ru,
            'en' => $en,
        ];
    }

    /**
     * @param  array{internal_image_fixture?: string, public_image_fixture?: string}  $itemRow
     */
    private function syncItemImages(MenuItem $item, array $itemRow): void
    {
        $this->syncSlot($item, MenuItemImageSlot::Internal, $itemRow['internal_image_fixture'] ?? null);
        $this->syncSlot($item, MenuItemImageSlot::Public, $itemRow['public_image_fixture'] ?? null);
    }

    private function syncSlot(MenuItem $item, MenuItemImageSlot $slot, ?string $fixture): void
    {
        if ($fixture === null) {
            app(RemoveMenuItemImage::class)((int) $item->id, $slot);

            return;
        }

        $path = database_path('fixtures/menu-images/'.$fixture);

        if (! is_file($path)) {
            throw new RuntimeException("Missing demo menu image fixture [{$fixture}].");
        }

        app(ReplaceMenuItemImage::class)(
            (int) $item->id,
            $slot,
            new UploadedFile($path, $fixture, 'image/png', null, true),
        );
    }
}
