<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TypeClient: string implements HasLabel
{
    case RESTAURANT = 'restaurant';
    case MAQUIS = 'maquis';
    case HOTEL = 'hotel';
    case SUPERMARCHE = 'supermarche';
    case ECOLE_CANTINE = 'ecole_cantine';
    case MENAGE = 'menage';
    case REVENDEUR = 'revendeur';
    case INDUSTRIEL = 'industriel';
    case AUTRE = 'autre';

    public function getLabel(): string
    {
        return match ($this) {
            self::RESTAURANT => 'Restaurant',
            self::MAQUIS => 'Maquis',
            self::HOTEL => 'Hôtel',
            self::SUPERMARCHE => 'Supermarché',
            self::ECOLE_CANTINE => 'École / Cantine',
            self::MENAGE => 'Ménage',
            self::REVENDEUR => 'Revendeur',
            self::INDUSTRIEL => 'Industriel',
            self::AUTRE => 'Autre',
        };
    }
}
