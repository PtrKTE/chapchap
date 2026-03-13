<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum CategorieProduit: string implements HasLabel
{
    case VOLAILLE = 'volaille';
    case DECOUPE = 'decoupe';
    case ABAT = 'abat';
    case OEUF = 'oeuf';
    case EAU = 'eau';
    case VIANDE = 'viande';

    public function getLabel(): string
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
