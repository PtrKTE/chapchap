<?php

declare(strict_types=1);

namespace App\Enums;

enum TypeEmplacement: string
{
    case SITE = 'site';
    case POINT_DE_VENTE = 'point_de_vente';
    case DEPOT = 'depot';

    public function label(): string
    {
        return match ($this) {
            self::SITE => 'Site',
            self::POINT_DE_VENTE => 'Point de vente',
            self::DEPOT => 'Dépôt',
        };
    }
}
