<?php

declare(strict_types=1);

namespace App\Enums;

enum Canal: string
{
    case BOUTIQUE = 'boutique';
    case COMMERCIAL = 'commercial';
    case LIVRAISON = 'livraison';
    case B2B = 'b2b';

    public function label(): string
    {
        return match ($this) {
            self::BOUTIQUE => 'Boutique',
            self::COMMERCIAL => 'Commercial',
            self::LIVRAISON => 'Livraison',
            self::B2B => 'B2B',
        };
    }
}
