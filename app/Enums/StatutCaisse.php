<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StatutCaisse: string implements HasLabel, HasColor
{
    case OUVERTE = 'ouverte';
    case CLOTUREE = 'cloturee';
    case VALIDEE = 'validee';

    public function getLabel(): string
    {
        return match ($this) {
            self::OUVERTE => 'Ouverte',
            self::CLOTUREE => 'Clôturée',
            self::VALIDEE => 'Validée',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::OUVERTE => 'warning',
            self::CLOTUREE => 'info',
            self::VALIDEE => 'success',
        };
    }
}
