<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Rapport Caisse {{ $caisse->date_caisse->format('d/m/Y') }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #333; padding: 20px; }

        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #f97316; padding-bottom: 10px; }
        .header h1 { font-size: 22px; color: #f97316; margin-bottom: 4px; }
        .header h2 { font-size: 14px; color: #333; margin-top: 6px; }
        .header p { font-size: 10px; color: #666; }

        .section { margin-bottom: 18px; }
        .section h3 { font-size: 12px; color: #f97316; border-bottom: 1px solid #fed7aa; padding-bottom: 3px; margin-bottom: 8px; }

        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        table.recap td { padding: 5px 10px; }
        table.recap .label { font-weight: bold; width: 250px; color: #555; }
        table.recap .montant { text-align: right; font-weight: bold; }

        table.detail { font-size: 10px; }
        table.detail th { background-color: #f97316; color: white; padding: 5px 6px; text-align: left; }
        table.detail td { padding: 4px 6px; border-bottom: 1px solid #eee; }
        table.detail tr:nth-child(even) { background-color: #fef3e2; }
        table.detail .num { text-align: right; }

        .encadre { border: 2px solid #f97316; border-radius: 6px; padding: 10px; margin: 12px 0; }
        .encadre-title { font-size: 12px; font-weight: bold; text-align: center; margin-bottom: 6px; color: #f97316; }

        .solde { font-size: 16px; text-align: center; font-weight: bold; margin: 8px 0; }
        .solde.positif { color: #166534; }
        .solde.negatif { color: #991b1b; }

        .ecart-ok { color: #166534; background: #dcfce7; padding: 6px; text-align: center; border-radius: 4px; }
        .ecart-ko { color: #991b1b; background: #fee2e2; padding: 6px; text-align: center; border-radius: 4px; }

        .statut { text-align: center; padding: 6px; border-radius: 4px; font-weight: bold; margin: 10px 0; font-size: 12px; }
        .statut-ouverte { background: #dbeafe; color: #1d4ed8; }
        .statut-cloturee { background: #fef9c3; color: #854d0e; }
        .statut-validee { background: #dcfce7; color: #166534; }

        .footer { text-align: center; margin-top: 20px; padding-top: 10px; border-top: 1px solid #ddd; font-size: 9px; color: #999; }

        .signatures { margin-top: 30px; }
        .signatures table td { padding: 15px 10px; text-align: center; width: 50%; border-top: 1px solid #ccc; }
        .signatures .nom { font-weight: bold; margin-top: 30px; }
    </style>
</head>
<body>
    {{-- En-tête --}}
    <div class="header">
        <h1>CHAPCHAP</h1>
        <h2>Rapport de Caisse</h2>
        <p>{{ $caisse->emplacement?->nom ?? '' }} - {{ $caisse->date_caisse->format('d/m/Y') }}</p>
    </div>

    {{-- Statut --}}
    @php
        $statutClass = match($caisse->statut?->value) {
            'validee' => 'statut-validee',
            'cloturee' => 'statut-cloturee',
            default => 'statut-ouverte',
        };
    @endphp
    <div class="statut {{ $statutClass }}">
        {{ strtoupper($caisse->statut?->getLabel() ?? 'OUVERTE') }}
    </div>

    {{-- Récap encaissements --}}
    <div class="section">
        <h3>Encaissements du jour</h3>
        <table class="recap">
            <tr>
                <td class="label">Encaissements (ventes du jour) :</td>
                <td class="montant">{{ number_format((float) $caisse->total_encaisse, 0, ',', ' ') }} FCFA</td>
            </tr>
            <tr>
                <td class="label">Crédits encaissés (ventes antérieures) :</td>
                <td class="montant">{{ number_format((float) $caisse->total_credits_encaisses, 0, ',', ' ') }} FCFA</td>
            </tr>
            <tr style="border-top: 1px solid #ddd;">
                <td class="label">Total encaissé :</td>
                <td class="montant" style="color: #166534;">{{ number_format((float) $caisse->total_encaisse + (float) $caisse->total_credits_encaisses, 0, ',', ' ') }} FCFA</td>
            </tr>
        </table>
    </div>

    {{-- Décaissements --}}
    <div class="section">
        <h3>Décaissements / Charges</h3>
        @if ($charges->isNotEmpty())
            <table class="detail">
                <thead>
                    <tr>
                        <th>Catégorie</th>
                        <th>Libellé</th>
                        <th class="num">Montant</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($charges as $charge)
                        <tr>
                            <td>{{ $charge->categorie?->nom ?? '' }}</td>
                            <td>{{ $charge->libelle }}</td>
                            <td class="num">{{ number_format((float) $charge->montant, 0, ',', ' ') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p style="color: #999; font-style: italic;">Aucune charge ce jour.</p>
        @endif
        <table class="recap">
            <tr>
                <td class="label">Total décaissé :</td>
                <td class="montant" style="color: #dc2626;">{{ number_format((float) $caisse->total_decaisse, 0, ',', ' ') }} FCFA</td>
            </tr>
        </table>
    </div>

    {{-- Solde --}}
    <div class="encadre">
        <div class="encadre-title">Solde de Caisse</div>
        <div class="solde {{ (float) $caisse->solde_caisse >= 0 ? 'positif' : 'negatif' }}">
            {{ number_format((float) $caisse->solde_caisse, 0, ',', ' ') }} FCFA
        </div>
    </div>

    {{-- Versements --}}
    <div class="section">
        <h3>Versements effectués</h3>
        <table class="recap">
            <tr>
                <td class="label">Espèces versées :</td>
                <td class="montant">{{ number_format((float) $caisse->montant_verse, 0, ',', ' ') }} FCFA</td>
            </tr>
            <tr>
                <td class="label">Dépôt Wave / MTN Money :</td>
                <td class="montant">{{ number_format((float) $caisse->depot_wave_mtn, 0, ',', ' ') }} FCFA</td>
            </tr>
            <tr>
                <td class="label">Dépôt chèque :</td>
                <td class="montant">{{ number_format((float) $caisse->depot_cheque, 0, ',', ' ') }} FCFA</td>
            </tr>
            <tr style="border-top: 1px solid #ddd;">
                <td class="label">Total versé :</td>
                <td class="montant">{{ number_format((float) $caisse->montant_verse + (float) $caisse->depot_wave_mtn + (float) $caisse->depot_cheque, 0, ',', ' ') }} FCFA</td>
            </tr>
        </table>
    </div>

    {{-- Écart --}}
    @php
        $ecart = (float) $caisse->ecart;
    @endphp
    <div class="{{ $ecart == 0 ? 'ecart-ok' : 'ecart-ko' }}">
        Écart : {{ number_format($ecart, 0, ',', ' ') }} FCFA
        @if ($ecart == 0)
            - Caisse équilibrée
        @elseif ($ecart > 0)
            - Excédent de caisse
        @else
            - Manquant de caisse
        @endif
    </div>

    {{-- Observations --}}
    @if ($caisse->observations)
        <div style="margin-top: 12px; padding: 8px; background: #f9fafb; border-left: 3px solid #f97316; font-size: 10px;">
            <strong>Observations :</strong> {{ $caisse->observations }}
        </div>
    @endif

    {{-- Signatures --}}
    <div class="signatures">
        <table>
            <tr>
                <td>
                    Responsable caisse<br><br><br>
                    <span class="nom">{{ $caisse->responsable?->name ?? '________________' }}</span>
                </td>
                <td>
                    Validé par<br><br><br>
                    <span class="nom">{{ $caisse->validePar?->name ?? '________________' }}</span>
                </td>
            </tr>
        </table>
    </div>

    {{-- Pied de page --}}
    <div class="footer">
        Rapport généré le {{ now()->format('d/m/Y à H:i') }} - CHAPCHAP - Boucherie & Transformation de Volailles
    </div>
</body>
</html>
