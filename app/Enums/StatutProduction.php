<?php

declare(strict_types=1);

namespace App\Enums;

enum StatutProduction: string
{
    case EN_COURS = 'en_cours';
    case VALIDEE = 'validee';
    case ANNULEE = 'annulee';

    public function label(): string
    {
        return match ($this) {
            self::EN_COURS => 'En cours',
            self::VALIDEE => 'Validée',
            self::ANNULEE => 'Annulée',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::EN_COURS => 'warning',
            self::VALIDEE => 'success',
            self::ANNULEE => 'danger',
        };
    }
}
