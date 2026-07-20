<?php

declare(strict_types=1);

namespace App\Modules\Menu\Domain;

enum MenuItemImageSlot: string
{
    case Internal = 'internal';
    case Public = 'public';

    public function column(): string
    {
        return match ($this) {
            self::Internal => 'internal_image',
            self::Public => 'public_image',
        };
    }
}
