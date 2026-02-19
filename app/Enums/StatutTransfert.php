<?php

declare(strict_types=1);

namespace App\Enums;

enum StatutTransfert: string
{
    case EN_COURS = 'en_cours';
    case RECU = 'recu';
    case ANNULE = 'annule';

    public function label(): string
    {
        return match ($this) {
            self::EN_COURS => 'En cours',
            self::RECU => 'Reçu',
            self::ANNULE => 'Annulé',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::EN_COURS => 'warning',
            self::RECU => 'success',
            self::ANNULE => 'danger',
        };
    }
}
