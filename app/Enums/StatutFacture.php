<?php

declare(strict_types=1);

namespace App\Enums;

enum StatutFacture: string
{
    case NON_REGLEE = 'non_reglee';
    case PARTIELLE = 'partielle';
    case REGLEE = 'reglee';

    public function label(): string
    {
        return match ($this) {
            self::NON_REGLEE => 'Non réglée',
            self::PARTIELLE => 'Partielle',
            self::REGLEE => 'Réglée',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::NON_REGLEE => 'danger',
            self::PARTIELLE => 'warning',
            self::REGLEE => 'success',
        };
    }
}
