<?php

declare(strict_types=1);

namespace App\Enums;

enum StatutPaiement: string
{
    case PAYE = 'paye';
    case PARTIEL = 'partiel';
    case CREDIT = 'credit';
    case REGLE_PATRONNE = 'regle_patronne';

    public function label(): string
    {
        return match ($this) {
            self::PAYE => 'Payé',
            self::PARTIEL => 'Partiel',
            self::CREDIT => 'Crédit',
            self::REGLE_PATRONNE => 'Réglé patronne',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PAYE => 'success',
            self::PARTIEL => 'warning',
            self::CREDIT => 'danger',
            self::REGLE_PATRONNE => 'info',
        };
    }
}
