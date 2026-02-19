<?php

declare(strict_types=1);

namespace App\Enums;

enum CategorieProduit: string
{
    case VOLAILLE = 'volaille';
    case DECOUPE = 'decoupe';
    case ABAT = 'abat';
    case OEUF = 'oeuf';
    case EAU = 'eau';
    case VIANDE = 'viande';

    public function label(): string
    {
        return match ($this) {
            self::VOLAILLE => 'Volaille',
            self::DECOUPE => 'Découpe',
            self::ABAT => 'Abat',
            self::OEUF => 'Œuf',
            self::EAU => 'Eau',
            self::VIANDE => 'Viande',
        };
    }
}
