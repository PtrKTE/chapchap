<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StatutProduction: string implements HasLabel, HasColor
{
    case EN_COURS = 'en_cours';
    case VALIDEE = 'validee';
    case ANNULEE = 'annulee';

    public function getLabel(): string
    {
        return match ($this) {
            self::EN_COURS => 'En cours',
            self::VALIDEE => 'Validée',
            self::ANNULEE => 'Annulée',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::EN_COURS => 'warning',
            self::VALIDEE => 'success',
            self::ANNULEE => 'danger',
        };
    }
}
