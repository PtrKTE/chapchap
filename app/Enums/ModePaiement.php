<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ModePaiement: string implements HasLabel
{
    case ESPECES = 'especes';
    case WAVE = 'wave';
    case MTN_MONEY = 'mtn_money';
    case CHEQUE = 'cheque';
    case VIREMENT = 'virement';

    public function getLabel(): string
    {
        return match ($this) {
            self::ESPECES => 'Espèces',
            self::WAVE => 'Wave',
            self::MTN_MONEY => 'MTN Money',
            self::CHEQUE => 'Chèque',
            self::VIREMENT => 'Virement',
        };
    }
}
