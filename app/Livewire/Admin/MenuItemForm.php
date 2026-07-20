<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Modules\Menu\Application\CreateMenuItem;
use App\Modules\Menu\Application\RemoveMenuItemImage;
use App\Modules\Menu\Application\ReplaceMenuItemImage;
use App\Modules\Menu\Application\UpdateMenuItem;
use App\Modules\Menu\Domain\MenuItemImageSlot;
use App\Modules\Menu\Infrastructure\Models\MenuCategory;
use App\Modules\Menu\Infrastructure\Models\MenuItem;
use App\Modules\Menu\Infrastructure\Storage\MenuItemImageUrlResolver;
use App\Support\I18n\LocalizedText;
use App\Support\Money\Money;
use App\Support\Money\MoneyFormatter;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Throwable;

final class MenuItemForm extends Component
{
    use WithFileUploads;

    /** @var array<int, string> */
    public array $categoryOptions = [];

    public ?int $itemId = null;

    public bool $isEdit = false;

    public ?int $category_id = null;

    public string $name_hy = '';

    public string $name_ru = '';

    public string $name_en = '';

    public string $description_hy = '';

    public string $description_ru = '';

    public string $description_en = '';

    public string $price_major = '0';

    public string $currency = 'AMD';

    public int $sort_order = 0;

    public bool $active = true;

    public ?TemporaryUploadedFile $internalUpload = null;

    public ?TemporaryUploadedFile $publicUpload = null;

    /** @var array<string, mixed>|null */
    public ?array $internalImage = null;

    /** @var array<string, mixed>|null */
    public ?array $publicImage = null;

    /**
     * @param  EloquentCollection<int, MenuCategory>  $categories
     */
    public function mount(EloquentCollection $categories, string $defaultCurrency, ?MenuItem $item = null): void
    {
        $this->categoryOptions = $categories
            ->mapWithKeys(fn (MenuCategory $category): array => [
                (int) $category->id => $category->translatedName()->forLocale(app()->getLocale()),
            ])
            ->all();

        $this->currency = $defaultCurrency;

        if (! $item instanceof MenuItem) {
            return;
        }

        $this->itemId = (int) $item->id;
        $this->isEdit = true;
        $this->category_id = (int) $item->category_id;
        $this->name_hy = $item->translatedName()->forLocale('hy', 'hy');
        $this->name_ru = $item->translatedName()->forLocale('ru', 'ru');
        $this->name_en = $item->translatedName()->forLocale('en', 'en');
        $this->description_hy = $item->translatedDescription()?->forLocale('hy', 'hy') ?? '';
        $this->description_ru = $item->translatedDescription()?->forLocale('ru', 'ru') ?? '';
        $this->description_en = $item->translatedDescription()?->forLocale('en', 'en') ?? '';
        $this->price_major = MoneyFormatter::toMajor($item->price());
        $this->currency = (string) $item->currency;
        $this->sort_order = (int) $item->sort_order;
        $this->active = (bool) $item->active;
        $this->internalImage = $this->metadata($item, MenuItemImageSlot::Internal);
        $this->publicImage = $this->metadata($item, MenuItemImageSlot::Public);
    }

    public function render(): View
    {
        return view('livewire.admin.menu.item-form');
    }

    public function save(): void
    {
        $this->validate($this->rules(), [], $this->validationAttributes());

        $item = $this->isEdit
            ? app(UpdateMenuItem::class)(
                $this->existingItemId(),
                $this->categoryId(),
                $this->localizedName(),
                $this->localizedDescription(),
                $this->price(),
                $this->sort_order,
                $this->active,
            )
            : app(CreateMenuItem::class)(
                $this->categoryId(),
                $this->localizedName(),
                $this->localizedDescription(),
                $this->price(),
                $this->sort_order,
                $this->active,
            );

        if ($this->internalUpload instanceof TemporaryUploadedFile) {
            $item = app(ReplaceMenuItemImage::class)((int) $item->id, MenuItemImageSlot::Internal, $this->internalUpload);
        }

        if ($this->publicUpload instanceof TemporaryUploadedFile) {
            $item = app(ReplaceMenuItemImage::class)((int) $item->id, MenuItemImageSlot::Public, $this->publicUpload);
        }

        session()->flash('status', $this->isEdit ? __('menu.flash.item_updated') : __('menu.flash.item_created'));

        $this->redirectRoute('admin.menu.index');
    }

    public function removeInternalImage(): void
    {
        $this->removeImage(MenuItemImageSlot::Internal);
    }

    public function removePublicImage(): void
    {
        $this->removeImage(MenuItemImageSlot::Public);
    }

    public function internalPreviewUrl(): string
    {
        return $this->previewUrl($this->internalUpload, $this->internalImage);
    }

    public function publicPreviewUrl(): string
    {
        return $this->previewUrl($this->publicUpload, $this->publicImage);
    }

    public function hasInternalImage(): bool
    {
        return $this->internalImage !== null || $this->internalUpload instanceof TemporaryUploadedFile;
    }

    public function hasPublicImage(): bool
    {
        return $this->publicImage !== null || $this->publicUpload instanceof TemporaryUploadedFile;
    }

    /**
     * @return array<string, list<string>>
     */
    protected function rules(): array
    {
        $configuredMaxUploadKb = config('menu_images.max_upload_kb', 4096);
        $maxUploadKb = is_int($configuredMaxUploadKb) ? (string) $configuredMaxUploadKb : '4096';

        return [
            'category_id' => ['required', 'integer', 'min:1'],
            'name_hy' => ['required', 'string', 'max:255'],
            'name_ru' => ['required', 'string', 'max:255'],
            'name_en' => ['required', 'string', 'max:255'],
            'description_hy' => ['nullable', 'required_with:description_ru,description_en', 'string', 'max:1000'],
            'description_ru' => ['nullable', 'required_with:description_hy,description_en', 'string', 'max:1000'],
            'description_en' => ['nullable', 'required_with:description_hy,description_ru', 'string', 'max:1000'],
            'price_major' => ['required', 'string', 'regex:/^\d+([.,]\d{1,2})?$/'],
            'currency' => ['required', 'string', 'regex:/^[A-Z]{3}$/'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'active' => ['boolean'],
            'internalUpload' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.$maxUploadKb],
            'publicUpload' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.$maxUploadKb],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'category_id' => __('menu.fields.category'),
            'name_hy' => __('menu.fields.name_hy'),
            'name_ru' => __('menu.fields.name_ru'),
            'name_en' => __('menu.fields.name_en'),
            'description_hy' => __('menu.fields.description_hy'),
            'description_ru' => __('menu.fields.description_ru'),
            'description_en' => __('menu.fields.description_en'),
            'price_major' => __('menu.fields.price_major'),
            'currency' => __('menu.fields.currency'),
            'sort_order' => __('menu.fields.sort_order'),
            'active' => __('menu.fields.active'),
            'internalUpload' => __('menu.fields.internal_image'),
            'publicUpload' => __('menu.fields.public_image'),
        ];
    }

    private function removeImage(MenuItemImageSlot $slot): void
    {
        if (! $this->isEdit) {
            if ($slot === MenuItemImageSlot::Internal) {
                $this->internalUpload = null;
            } else {
                $this->publicUpload = null;
            }

            return;
        }

        $item = app(RemoveMenuItemImage::class)($this->existingItemId(), $slot);

        if ($slot === MenuItemImageSlot::Internal) {
            $this->internalUpload = null;
            $this->internalImage = $this->metadata($item, $slot);
        } else {
            $this->publicUpload = null;
            $this->publicImage = $this->metadata($item, $slot);
        }

        session()->flash('status', $slot === MenuItemImageSlot::Internal
            ? __('menu.flash.internal_image_removed')
            : __('menu.flash.public_image_removed'));
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    private function previewUrl(?TemporaryUploadedFile $upload, ?array $metadata): string
    {
        if ($upload instanceof TemporaryUploadedFile) {
            try {
                $url = $upload->temporaryUrl();
            } catch (Throwable) {
                $url = null;
            }

            if (is_string($url)) {
                return $url;
            }
        }

        $resolver = app(MenuItemImageUrlResolver::class);

        return $resolver->metadataImageUrl($metadata);
    }

    private function categoryId(): int
    {
        return (int) $this->category_id;
    }

    private function existingItemId(): int
    {
        if ($this->itemId === null) {
            abort(404);
        }

        return $this->itemId;
    }

    private function localizedName(): LocalizedText
    {
        return LocalizedText::fromArray([
            'hy' => $this->name_hy,
            'ru' => $this->name_ru,
            'en' => $this->name_en,
        ]);
    }

    private function localizedDescription(): ?LocalizedText
    {
        $description = [
            'hy' => $this->description_hy,
            'ru' => $this->description_ru,
            'en' => $this->description_en,
        ];

        if (trim($description['hy'].$description['ru'].$description['en']) === '') {
            return null;
        }

        return LocalizedText::fromArray($description);
    }

    private function price(): Money
    {
        return new Money(
            MoneyFormatter::minorFromMajor($this->price_major, $this->currency),
            $this->currency,
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function metadata(MenuItem $item, MenuItemImageSlot $slot): ?array
    {
        $metadata = $item->getAttribute($slot->column());

        if (! is_array($metadata)) {
            return null;
        }

        /** @var array<string, mixed> $metadata */
        return $metadata;
    }
}
