<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TypeEmplacement: string implements HasLabel
{
    case SITE = 'site';
    case POINT_DE_VENTE = 'point_de_vente';
    case DEPOT = 'depot';

    public function getLabel(): string
    {
        return match ($this) {
            self::SITE => 'Site',
            self::POINT_DE_VENTE => 'Point de vente',
            self::DEPOT => 'Dépôt',
        };
    }
}
