<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StatutFacture: string implements HasLabel, HasColor
{
    case NON_REGLEE = 'non_reglee';
    case PARTIELLE = 'partielle';
    case REGLEE = 'reglee';

    public function getLabel(): string
    {
        return match ($this) {
            self::NON_REGLEE => 'Non réglée',
            self::PARTIELLE => 'Partielle',
            self::REGLEE => 'Réglée',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::NON_REGLEE => 'danger',
            self::PARTIELLE => 'warning',
            self::REGLEE => 'success',
        };
    }
}
