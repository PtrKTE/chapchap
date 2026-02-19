<?php

declare(strict_types=1);

namespace App\Enums;

enum Profil: string
{
    case GERANT = 'gerant';
    case RESP_OPERATIONS = 'resp_operations';
    case GESTIONNAIRE_STOCK = 'gestionnaire_stock';
    case AGENT_PRODUCTION = 'agent_production';
    case COMMERCIAL = 'commercial';
    case POINT_DE_VENTE = 'point_de_vente';

    /**
     * Libellé en français pour l'interface.
     */
    public function label(): string
    {
        return match ($this) {
            self::GERANT => 'Gérant',
            self::RESP_OPERATIONS => 'Resp. Opérations & Admin',
            self::GESTIONNAIRE_STOCK => 'Gestionnaire Stock & Hygiène',
            self::AGENT_PRODUCTION => 'Agent de production',
            self::COMMERCIAL => 'Commercial',
            self::POINT_DE_VENTE => 'Point de vente',
        };
    }
}
