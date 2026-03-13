<?php

declare(strict_types=1);

namespace App\Filament\Resources\CaisseResource\Pages;

use App\Enums\StatutCaisse;
use App\Filament\Resources\CaisseResource;
use App\Models\Charge;
use App\Models\Paiement;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

/**
 * Page d'édition d'une caisse.
 *
 * Contient les actions métier :
 * - "Recalculer" : recalcule les totaux à partir des paiements et charges du jour
 * - "Clôturer" : ferme la caisse (plus de modification possible sauf par le gérant)
 * - "Valider" : le gérant valide la caisse clôturée
 */
class EditCaisse extends EditRecord
{
    protected static string $resource = CaisseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Imprimer le rapport de caisse en PDF
            Actions\Action::make('imprimer_rapport')
                ->label('Imprimer rapport')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->action(function () {
                    $caisse = $this->record->load(['emplacement', 'responsable', 'validePar']);
                    $charges = Charge::whereDate('date_charge', $caisse->date_caisse)
                        ->where('emplacement_id', $caisse->emplacement_id)
                        ->with('categorie')
                        ->get();

                    $pdf = Pdf::loadView('pdf.rapport-caisse', [
                        'caisse' => $caisse,
                        'charges' => $charges,
                    ]);

                    return response()->streamDownload(
                        fn() => print($pdf->output()),
                        "caisse_{$caisse->date_caisse->format('Y-m-d')}_{$caisse->emplacement?->nom}.pdf"
                    );
                }),

            // Recalculer les totaux à partir de la BDD
            Actions\Action::make('recalculer')
                ->label('Recalculer les totaux')
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->visible(fn() => $this->record->statut === StatutCaisse::OUVERTE)
                ->action(fn() => $this->recalculerTotaux()),

            // Clôturer la caisse en fin de journée
            Actions\Action::make('cloturer')
                ->label('Clôturer la caisse')
                ->icon('heroicon-o-lock-closed')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Clôturer la caisse')
                ->modalDescription('Une fois clôturée, la caisse ne pourra plus être modifiée (sauf par le gérant). Continuer ?')
                ->visible(fn() => $this->record->statut === StatutCaisse::OUVERTE)
                ->action(fn() => $this->cloturerCaisse()),

            // Valider la caisse (gérant uniquement)
            Actions\Action::make('valider')
                ->label('Valider la caisse')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn() => $this->record->statut === StatutCaisse::CLOTUREE)
                ->action(fn() => $this->validerCaisse()),
        ];
    }

    /**
     * Recalcule les totaux à partir des paiements et charges du jour.
     *
     * total_encaisse = somme des paiements du jour sur des ventes du jour
     * total_credits_encaisses = somme des paiements du jour sur des ventes antérieures
     * total_decaisse = somme des charges du jour sur cet emplacement
     */
    protected function recalculerTotaux(): void
    {
        $caisse = $this->record;
        $date = $caisse->date_caisse;
        $emplacementId = $caisse->emplacement_id;

        // Encaissements : paiements du jour sur des ventes de cet emplacement
        $paiementsJour = Paiement::whereDate('date_paiement', $date)
            ->whereHas('vente', fn($q) => $q->where('emplacement_id', $emplacementId)->where('annulee', false))
            ->get();

        // Séparer : encaissements du jour vs crédits encaissés (ventes antérieures)
        $encaisseJour = 0;
        $creditsEncaisses = 0;

        foreach ($paiementsJour as $paiement) {
            $vente = $paiement->vente;
            if ($vente && $vente->date_vente->toDateString() === $date->toDateString()) {
                $encaisseJour += (float) $paiement->montant;
            } else {
                $creditsEncaisses += (float) $paiement->montant;
            }
        }

        // Décaissements : charges du jour sur cet emplacement
        $decaisseJour = Charge::whereDate('date_charge', $date)
            ->where('emplacement_id', $emplacementId)
            ->sum('montant');

        // Mettre à jour
        $solde = $encaisseJour + $creditsEncaisses - (float) $decaisseJour;
        $verse = (float) $caisse->montant_verse;
        $wave = (float) $caisse->depot_wave_mtn;
        $cheque = (float) $caisse->depot_cheque;
        $ecart = $solde - ($verse + $wave + $cheque);

        $caisse->update([
            'total_encaisse' => round($encaisseJour, 2),
            'total_credits_encaisses' => round($creditsEncaisses, 2),
            'total_decaisse' => round((float) $decaisseJour, 2),
            'solde_caisse' => round($solde, 2),
            'ecart' => round($ecart, 2),
        ]);

        Notification::make()
            ->title('Totaux recalculés')
            ->body(
                'Encaissé: ' . number_format($encaisseJour, 0, ',', ' ')
                . ' | Crédits: ' . number_format($creditsEncaisses, 0, ',', ' ')
                . ' | Décaissé: ' . number_format((float) $decaisseJour, 0, ',', ' ')
                . ' | Solde: ' . number_format($solde, 0, ',', ' ') . ' FCFA'
            )
            ->success()
            ->send();
    }

    /**
     * Clôturer la caisse — empêche les modifications.
     */
    protected function cloturerCaisse(): void
    {
        // Recalculer avant de clôturer
        $this->recalculerTotaux();
        $this->record->refresh();

        $this->record->update([
            'statut' => StatutCaisse::CLOTUREE->value,
        ]);

        Notification::make()
            ->title('Caisse clôturée')
            ->body("La caisse du {$this->record->date_caisse->format('d/m/Y')} est clôturée.")
            ->warning()
            ->send();
    }

    /**
     * Valider la caisse (gérant uniquement).
     */
    protected function validerCaisse(): void
    {
        $this->record->update([
            'statut' => StatutCaisse::VALIDEE->value,
            'valide_par' => auth()->id(),
        ]);

        Notification::make()
            ->title('Caisse validée')
            ->body("La caisse du {$this->record->date_caisse->format('d/m/Y')} a été validée.")
            ->success()
            ->send();
    }

    /**
     * Recalcule solde et écart à la sauvegarde.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $encaisse = (float) ($data['total_encaisse'] ?? 0);
        $credits = (float) ($data['total_credits_encaisses'] ?? 0);
        $decaisse = (float) ($data['total_decaisse'] ?? 0);
        $solde = $encaisse + $credits - $decaisse;

        $verse = (float) ($data['montant_verse'] ?? 0);
        $wave = (float) ($data['depot_wave_mtn'] ?? 0);
        $cheque = (float) ($data['depot_cheque'] ?? 0);
        $ecart = $solde - ($verse + $wave + $cheque);

        $data['solde_caisse'] = round($solde, 2);
        $data['ecart'] = round($ecart, 2);

        return $data;
    }
}
