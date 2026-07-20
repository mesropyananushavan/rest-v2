<?php

declare(strict_types=1);

namespace App\Modules\Menu\Infrastructure\Storage;

use App\Modules\Menu\Domain\MenuItemImageSlot;
use App\Modules\Menu\Infrastructure\Models\MenuItem;
use Illuminate\Support\Facades\Storage;
use UnexpectedValueException;

final class MenuItemImageUrlResolver
{
    public function thumbnailUrl(MenuItem $item, MenuItemImageSlot $slot): string
    {
        return $this->urlFor($this->metadata($item, $slot)['thumbnail_path'] ?? null);
    }

    public function imageUrl(MenuItem $item, MenuItemImageSlot $slot): string
    {
        return $this->urlFor($this->metadata($item, $slot)['path'] ?? null);
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function metadataThumbnailUrl(?array $metadata): string
    {
        return $this->urlFor($metadata['thumbnail_path'] ?? null);
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function metadataImageUrl(?array $metadata): string
    {
        return $this->urlFor($metadata['path'] ?? null);
    }

    public function placeholderUrl(): string
    {
        $placeholder = config('menu_images.placeholder');

        if (! is_string($placeholder) || $placeholder === '') {
            throw new UnexpectedValueException('Menu image placeholder is not configured.');
        }

        return asset($placeholder);
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

    private function urlFor(mixed $path): string
    {
        if (! is_string($path) || $path === '') {
            return $this->placeholderUrl();
        }

        return Storage::disk($this->disk())->url($path);
    }

    private function disk(): string
    {
        $disk = config('menu_images.disk');

        if (! is_string($disk) || $disk === '') {
            throw new UnexpectedValueException('Menu image disk is not configured.');
        }

        return $disk;
    }
}
