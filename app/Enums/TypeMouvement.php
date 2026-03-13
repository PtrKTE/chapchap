<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TypeMouvement: string implements HasLabel
{
    case ENTREE_PRODUCTION = 'entree_production';
    case ENTREE_ACHAT = 'entree_achat';
    case SORTIE_VENTE = 'sortie_vente';
    case SORTIE_PERTE = 'sortie_perte';
    case TRANSFERT_SORTIE = 'transfert_sortie';
    case TRANSFERT_ENTREE = 'transfert_entree';
    case AJUSTEMENT_PLUS = 'ajustement_plus';
    case AJUSTEMENT_MOINS = 'ajustement_moins';
    case ENTREE_ANNULATION = 'entree_annulation';

    public function getLabel(): string
    {
        return match ($this) {
            self::ENTREE_PRODUCTION => 'Entrée production',
            self::ENTREE_ACHAT => 'Entrée achat',
            self::SORTIE_VENTE => 'Sortie vente',
            self::SORTIE_PERTE => 'Sortie perte',
            self::TRANSFERT_SORTIE => 'Transfert sortie',
            self::TRANSFERT_ENTREE => 'Transfert entrée',
            self::AJUSTEMENT_PLUS => 'Ajustement (+)',
            self::AJUSTEMENT_MOINS => 'Ajustement (-)',
            self::ENTREE_ANNULATION => 'Entrée annulation vente',
        };
    }

    public function isEntree(): bool
    {
        return in_array($this, [
            self::ENTREE_PRODUCTION,
            self::ENTREE_ACHAT,
            self::TRANSFERT_ENTREE,
            self::AJUSTEMENT_PLUS,
            self::ENTREE_ANNULATION,
        ]);
    }

    public function isSortie(): bool
    {
        return ! $this->isEntree();
    }
}
