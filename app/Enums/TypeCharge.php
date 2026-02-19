<?php

declare(strict_types=1);

namespace App\Enums;

enum TypeCharge: string
{
    case FIXE = 'fixe';
    case VARIABLE = 'variable';

    public function label(): string
    {
        return match ($this) {
            self::FIXE => 'Fixe',
            self::VARIABLE => 'Variable',
        };
    }
}
