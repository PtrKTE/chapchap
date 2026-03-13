<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TypeCharge: string implements HasLabel
{
    case FIXE = 'fixe';
    case VARIABLE = 'variable';

    public function getLabel(): string
    {
        return match ($this) {
            self::FIXE => 'Fixe',
            self::VARIABLE => 'Variable',
        };
    }
}
