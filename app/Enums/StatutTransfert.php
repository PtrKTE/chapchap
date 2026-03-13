<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StatutTransfert: string implements HasLabel, HasColor
{
    case EN_COURS = 'en_cours';
    case RECU = 'recu';
    case ANNULE = 'annule';

    public function getLabel(): string
    {
        return match ($this) {
            self::EN_COURS => 'En cours',
            self::RECU => 'Reçu',
            self::ANNULE => 'Annulé',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::EN_COURS => 'warning',
            self::RECU => 'success',
            self::ANNULE => 'danger',
        };
    }
}
