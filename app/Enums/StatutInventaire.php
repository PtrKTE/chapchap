<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StatutInventaire: string implements HasLabel, HasColor
{
    case EN_COURS = 'en_cours';
    case TERMINE = 'termine';
    case VALIDE = 'valide';

    public function getLabel(): string
    {
        return match ($this) {
            self::EN_COURS => 'En cours',
            self::TERMINE => 'Terminé',
            self::VALIDE => 'Validé',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::EN_COURS => 'warning',
            self::TERMINE => 'info',
            self::VALIDE => 'success',
        };
    }
}
