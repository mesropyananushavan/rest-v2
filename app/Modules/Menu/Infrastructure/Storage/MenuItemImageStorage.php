<?php

declare(strict_types=1);

namespace App\Modules\Menu\Infrastructure\Storage;

use App\Modules\Menu\Domain\MenuItemImageSlot;
use App\Modules\Menu\Infrastructure\Models\MenuItem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Format;
use Intervention\Image\Laravel\Facades\Image;
use InvalidArgumentException;
use UnexpectedValueException;

final class MenuItemImageStorage
{
    /**
     * @return array{path: string, thumbnail_path: string, mime_type: string, width: int, height: int, size: int}
     */
    public function store(MenuItem $item, MenuItemImageSlot $slot, UploadedFile $file): array
    {
        $this->assertAllowed($file);

        $extension = $this->extension($file);
        $format = Format::create($extension);
        $basePath = $this->basePath($item, $slot);
        $token = (string) Str::ulid();
        $path = "{$basePath}/original-{$token}.{$extension}";
        $thumbnailPath = "{$basePath}/thumb-{$token}.{$extension}";

        $sourcePath = $file->getRealPath();

        if (! is_string($sourcePath)) {
            throw new InvalidArgumentException('Uploaded menu item image is not readable.');
        }

        $image = Image::decodePath($sourcePath)->scaleDown(
            width: $this->configInt('menu_images.original.max_width'),
            height: $this->configInt('menu_images.original.max_height'),
        );

        $original = $image->encodeUsingFormat(
            $format,
            quality: $this->configInt('menu_images.original.quality'),
        )->toString();

        Storage::disk($this->disk())->put($path, $original, [
            'visibility' => 'public',
        ]);

        $thumbnail = Image::decodePath($sourcePath)
            ->cover(
                $this->configInt('menu_images.thumbnail.width'),
                $this->configInt('menu_images.thumbnail.height'),
            )
            ->encodeUsingFormat(
                $format,
                quality: $this->configInt('menu_images.thumbnail.quality'),
            )
            ->toString();

        Storage::disk($this->disk())->put($thumbnailPath, $thumbnail, [
            'visibility' => 'public',
        ]);

        return [
            'path' => $path,
            'thumbnail_path' => $thumbnailPath,
            'mime_type' => $format->mediaType()->value,
            'width' => $image->width(),
            'height' => $image->height(),
            'size' => strlen($original),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function delete(?array $metadata): void
    {
        if ($metadata === null) {
            return;
        }

        $paths = array_values(array_filter([
            $metadata['path'] ?? null,
            $metadata['thumbnail_path'] ?? null,
        ], fn (mixed $path): bool => is_string($path) && $path !== ''));

        if ($paths === []) {
            return;
        }

        Storage::disk($this->disk())->delete($paths);
    }

    private function assertAllowed(UploadedFile $file): void
    {
        $maxBytes = $this->configInt('menu_images.max_upload_kb') * 1024;
        $size = $file->getSize();

        if (is_int($size) && $size > $maxBytes) {
            throw new InvalidArgumentException('Uploaded menu item image is too large.');
        }

        $mimeType = $file->getMimeType();
        $allowedMimes = $this->configStringList('menu_images.allowed_mimes');

        if ($mimeType !== null && ! in_array($mimeType, $allowedMimes, true)) {
            throw new InvalidArgumentException('Uploaded menu item image type is not supported.');
        }

        $extension = $this->extension($file);

        if (! in_array($extension, $this->configStringList('menu_images.allowed_extensions'), true)) {
            throw new InvalidArgumentException('Uploaded menu item image extension is not supported.');
        }
    }

    private function extension(UploadedFile $file): string
    {
        $extension = strtolower($file->extension() ?: $file->guessExtension() ?: $file->getClientOriginalExtension());

        if ($extension === 'jpeg') {
            return 'jpg';
        }

        if ($extension === '') {
            throw new InvalidArgumentException('Uploaded menu item image has no extension.');
        }

        return $extension;
    }

    private function basePath(MenuItem $item, MenuItemImageSlot $slot): string
    {
        $template = config('menu_images.path_template');

        if (! is_string($template) || $template === '') {
            throw new UnexpectedValueException('Menu image path template is not configured.');
        }

        return strtr($template, [
            '{tenant_id}' => (string) $item->tenant_id,
            '{item_id}' => (string) $item->id,
            '{slot}' => $slot->value,
        ]);
    }

    private function disk(): string
    {
        $disk = config('menu_images.disk');

        if (! is_string($disk) || $disk === '') {
            throw new UnexpectedValueException('Menu image disk is not configured.');
        }

        return $disk;
    }

    private function configInt(string $key): int
    {
        $value = config($key);

        if (! is_int($value)) {
            throw new UnexpectedValueException("Configuration value [{$key}] must be an integer.");
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    private function configStringList(string $key): array
    {
        $value = config($key);

        if (! is_array($value)) {
            throw new UnexpectedValueException("Configuration value [{$key}] must be a string list.");
        }

        return array_values(array_filter($value, fn (mixed $item): bool => is_string($item)));
    }
}
