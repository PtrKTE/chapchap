<?php

declare(strict_types=1);

namespace App\Enums;

enum StatutCaisse: string
{
    case OUVERTE = 'ouverte';
    case CLOTUREE = 'cloturee';
    case VALIDEE = 'validee';

    public function label(): string
    {
        return match ($this) {
            self::OUVERTE => 'Ouverte',
            self::CLOTUREE => 'Clôturée',
            self::VALIDEE => 'Validée',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::OUVERTE => 'warning',
            self::CLOTUREE => 'info',
            self::VALIDEE => 'success',
        };
    }
}
