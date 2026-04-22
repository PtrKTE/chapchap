<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Reçu {{ $vente->numero_recu }}</title>
    <style>
        /* ===== FORMAT TICKET DE CAISSE — 80mm (226.77pt) ===== */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 8px;
            color: #333;
            width: 100%;
            /* Padding minimal pour maximiser l'espace utile sur 80mm */
            padding: 4px 6px;
        }

        /* ── En-tête ── */
        .header {
            text-align: center;
            padding-bottom: 4px;
            border-bottom: 1px dashed #333;
            margin-bottom: 4px;
        }
        .header img { width: 45px; height: auto; margin-bottom: 2px; }
        .header .nom { font-size: 13px; font-weight: bold; color: #f97316; }
        .header .sub { font-size: 6.5px; color: #666; }
        .header .tel { font-size: 6.5px; color: #888; margin-top: 1px; }

        /* ── Numéro reçu ── */
        .recu-num {
            text-align: center;
            font-size: 10px;
            font-weight: bold;
            color: #f97316;
            padding: 3px 0;
            border-bottom: 1px dashed #333;
            margin-bottom: 4px;
        }

        /* ── Infos client ── */
        .info { margin-bottom: 4px; }
        .info table { width: 100%; }
        .info td { padding: 1px 0; font-size: 7.5px; }
        .info .lbl { font-weight: bold; color: #555; width: 50px; }

        /* ── Séparateur ── */
        .sep { border: none; border-top: 1px dashed #999; margin: 3px 0; }

        /* ── Tableau produits ── */
        table.prod { width: 100%; border-collapse: collapse; }
        table.prod th {
            font-size: 6.5px;
            text-transform: uppercase;
            color: #888;
            border-bottom: 1px solid #ccc;
            padding: 2px 0;
        }
        table.prod th.l { text-align: left; }
        table.prod th.r { text-align: right; }
        table.prod td { padding: 2px 0; font-size: 7.5px; border-bottom: 1px dotted #eee; }
        table.prod td.r { text-align: right; }
        table.prod td.nom { font-weight: bold; max-width: 75px; overflow: hidden; }

        /* ── Totaux ── */
        .totaux { width: 100%; border-collapse: collapse; margin-top: 3px; }
        .totaux td { padding: 1px 0; font-size: 8px; }
        .totaux .lbl { text-align: left; font-weight: bold; }
        .totaux .val { text-align: right; }
        .totaux .net td {
            font-size: 11px;
            font-weight: bold;
            color: #f97316;
            border-top: 2px solid #f97316;
            border-bottom: 2px solid #f97316;
            padding: 3px 0;
        }
        .totaux .reste td { color: #dc2626; font-weight: bold; }

        /* ── Statut ── */
        .statut {
            text-align: center;
            padding: 3px;
            margin: 4px 0;
            font-size: 9px;
            font-weight: bold;
            border-radius: 3px;
        }
        .st-ok { background: #dcfce7; color: #166534; }
        .st-part { background: #fef9c3; color: #854d0e; }
        .st-cred { background: #fee2e2; color: #991b1b; }

        /* ── Observations ── */
        .obs {
            font-size: 6.5px;
            padding: 3px;
            background: #f9fafb;
            border-left: 2px solid #f97316;
            margin: 3px 0;
        }

        /* ── Pied de page ── */
        .footer {
            text-align: center;
            margin-top: 6px;
            padding-top: 4px;
            border-top: 1px dashed #333;
        }
        .footer .merci { font-size: 9px; font-weight: bold; color: #f97316; }
        .footer .gen { font-size: 6px; color: #aaa; margin-top: 2px; }
    </style>
</head>
<body>

    {{-- EN-TÊTE --}}
    {{-- mPDF charge les images via chemin absolu (file://) --}}
    <div class="header">
        @php $logoPath = public_path('images/Logo_chapchap.jpeg'); @endphp
        @if (file_exists($logoPath))
            <img src="{{ 'file://' . $logoPath }}" alt="Logo">
        @endif
        <div class="nom">CHAP-CHAP</div>
        <div class="sub">Boucherie & Transformation de Volailles</div>
        <div class="tel">Abidjan, Côte d'Ivoire - Tél: 07 00 00 00 00</div>
    </div>

    {{-- NUMÉRO REÇU --}}
    <div class="recu-num">{{ $vente->numero_recu }}</div>

    {{-- INFOS VENTE / CLIENT --}}
    <div class="info">
        <table>
            <tr><td class="lbl">Date :</td><td>{{ $vente->date_vente->format('d/m/Y H:i') }}</td></tr>
            <tr><td class="lbl">Client :</td><td>{{ $vente->client?->nom ?? 'Client comptoir' }}</td></tr>
            @if ($vente->client?->telephone)
            <tr><td class="lbl">Tél :</td><td>{{ $vente->client->telephone }}</td></tr>
            @endif
            <tr><td class="lbl">Canal :</td><td>{{ $vente->canal?->getLabel() ?? '' }}</td></tr>
            @if ($vente->commercial)
            <tr><td class="lbl">Vendeur :</td><td>{{ $vente->commercial->name }}</td></tr>
            @endif
            <tr><td class="lbl">Site :</td><td>{{ $vente->emplacement?->nom ?? '' }}</td></tr>
        </table>
    </div>

    <hr class="sep">

    {{-- LIGNES DE VENTE --}}
    <table class="prod">
        <thead>
            <tr>
                <th class="l">Produit</th>
                <th class="r">Qté</th>
                <th class="r">P.U.</th>
                <th class="r">Montant</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($vente->lignes as $ligne)
            <tr>
                <td class="nom">{{ $ligne->produit?->nom ?? '' }}</td>
                <td class="r">{{ number_format((float) $ligne->quantite, 2, ',', '') }}</td>
                <td class="r">{{ number_format((float) $ligne->prix_unitaire, 0, ' ', ' ') }}</td>
                <td class="r">{{ number_format((float) $ligne->montant_ligne, 0, ' ', ' ') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <hr class="sep">

    {{-- TOTAUX --}}
    <table class="totaux">
        <tr>
            <td class="lbl">Total :</td>
            <td class="val">{{ number_format((float) $vente->montant_total, 0, ',', ' ') }} F</td>
        </tr>
        @if ((float) $vente->remise > 0)
        <tr>
            <td class="lbl">Remise :</td>
            <td class="val">- {{ number_format((float) $vente->remise, 0, ',', ' ') }} F</td>
        </tr>
        @endif
        <tr class="net">
            <td class="lbl">NET A PAYER :</td>
            <td class="val">{{ number_format((float) $vente->montant_net, 0, ',', ' ') }} F</td>
        </tr>
        <tr>
            <td class="lbl">Reçu :</td>
            <td class="val">{{ number_format((float) $vente->montant_recu, 0, ',', ' ') }} F</td>
        </tr>
        @if ((float) $vente->montant_restant > 0)
        <tr class="reste">
            <td class="lbl">RESTE :</td>
            <td class="val">{{ number_format((float) $vente->montant_restant, 0, ',', ' ') }} F</td>
        </tr>
        @endif
        <tr>
            <td class="lbl">Paiement :</td>
            <td class="val">{{ $vente->mode_paiement?->getLabel() ?? '' }}</td>
        </tr>
    </table>

    {{-- STATUT PAIEMENT --}}
    @php
        $stClass = match($vente->statut_paiement?->value) {
            'paye', 'regle_patronne' => 'st-ok',
            'partiel' => 'st-part',
            default => 'st-cred',
        };
    @endphp
    <div class="statut {{ $stClass }}">
        {{ strtoupper($vente->statut_paiement?->getLabel() ?? 'NON DÉFINI') }}
    </div>

    {{-- OBSERVATIONS --}}
    @if ($vente->observations)
    <div class="obs"><strong>Note :</strong> {{ $vente->observations }}</div>
    @endif

    {{-- PIED DE PAGE --}}
    <div class="footer">
        <div class="merci">Merci pour votre confiance !</div>
        <div class="gen">Reçu généré le {{ now()->format('d/m/Y à H:i') }}</div>
    </div>

</body>
</html>
