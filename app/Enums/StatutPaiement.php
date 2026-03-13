<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StatutPaiement: string implements HasLabel, HasColor
{
    case PAYE = 'paye';
    case PARTIEL = 'partiel';
    case CREDIT = 'credit';
    case REGLE_PATRONNE = 'regle_patronne';

    public function getLabel(): string
    {
        return match ($this) {
            self::PAYE => 'Payé',
            self::PARTIEL => 'Partiel',
            self::CREDIT => 'Crédit',
            self::REGLE_PATRONNE => 'Réglé patronne',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PAYE => 'success',
            self::PARTIEL => 'warning',
            self::CREDIT => 'danger',
            self::REGLE_PATRONNE => 'info',
        };
    }
}
