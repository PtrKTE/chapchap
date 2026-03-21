<?php

declare(strict_types=1);

namespace App\Filament\Resources\VenteResource\Pages;

use App\Enums\ModePaiement;
use App\Enums\StatutPaiement;
use App\Enums\TypeMouvement;
use App\Filament\Resources\VenteResource;
use App\Models\Paiement;
use App\Services\StockService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Page d'édition d'une vente.
 *
 * Contient les actions métier :
 * - "Encaisser un paiement" : pour les ventes à crédit, enregistrer un paiement partiel
 * - "Annuler la vente" : annule la vente et remet le stock (mouvement inverse)
 * - "Réglé par la patronne" : marque la vente comme réglée par la direction
 */
class EditVente extends EditRecord
{
    protected static string $resource = VenteResource::class;

    /**
     * Avant la sauvegarde : recalculer les totaux et s'assurer qu'aucun champ
     * numérique obligatoire n'est null (même problème que la création).
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Garantir que remise n'est jamais null
        $data['remise']       = (float) ($data['remise'] ?? 0);
        $data['montant_recu'] = (float) ($data['montant_recu'] ?? 0);

        // Les vrais totaux seront recalculés dans afterSave()
        // depuis les lignes sauvegardées (même logique que CreateVente)
        return $data;
    }

    /**
     * Après la sauvegarde : recalcul des totaux depuis les lignes réelles en BDD.
     *
     * Même raison que CreateVente : avec ->relationship() sur le Repeater,
     * Filament sauvegarde les lignes après le parent. afterSave() s'exécute
     * après les deux, donc les lignes sont disponibles.
     */
    protected function afterSave(): void
    {
        $vente = $this->record->fresh();

        $lignes         = $vente->lignes()->get();
        $montantTotal   = (float) $lignes->sum('montant_ligne');
        $remise         = (float) $vente->remise;
        $montantNet     = max(0, $montantTotal - $remise);
        $montantRecu    = (float) $vente->montant_recu;
        $montantRestant = max(0, $montantNet - $montantRecu);

        if ($montantNet <= 0) {
            $statut = StatutPaiement::PAYE->value;
        } elseif ($montantRecu <= 0) {
            $statut = StatutPaiement::CREDIT->value;
        } elseif ($montantRecu < $montantNet) {
            $statut = StatutPaiement::PARTIEL->value;
        } else {
            $statut = StatutPaiement::PAYE->value;
        }

        $vente->updateQuietly([
            'montant_total'          => round($montantTotal, 2),
            'montant_net'            => round($montantNet, 2),
            'montant_restant'        => round($montantRestant, 2),
            'statut_paiement'        => $statut,
            'date_reglement_complet' => $montantRestant <= 0 && $montantNet > 0
                                            ? now()->toDateString()
                                            : null,
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            // Bouton "Imprimer reçu PDF" — redirige vers la route dédiée (mPDF)
            Actions\Action::make('imprimer_recu')
                ->label('Imprimer reçu')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn () => route('vente.recu-pdf', $this->record))
                ->openUrlInNewTab(),

            // Bouton "Encaisser un paiement" — pour les ventes à crédit ou partielles
            Actions\Action::make('encaisser')
                ->label('Encaisser un paiement')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->visible(fn() => ! $this->record->annulee
                    && in_array($this->record->statut_paiement, [StatutPaiement::CREDIT, StatutPaiement::PARTIEL])
                )
                ->form([
                    Forms\Components\TextInput::make('montant')
                        ->label('Montant à encaisser (FCFA)')
                        ->numeric()
                        ->required()
                        ->minValue(1)
                        ->suffix('FCFA')
                        ->default(fn() => (float) $this->record->montant_restant)
                        ->helperText(fn() => 'Restant dû : ' . number_format((float) $this->record->montant_restant, 0, ',', ' ') . ' FCFA'),
                    Forms\Components\Select::make('mode_paiement')
                        ->label('Mode de paiement')
                        ->options(collect(ModePaiement::cases())->mapWithKeys(fn($e) => [$e->value => $e->getLabel()]))
                        ->required()
                        ->default('especes'),
                    Forms\Components\TextInput::make('reference_paiement')
                        ->label('Référence paiement')
                        ->helperText('N° transaction Wave/MTN, n° chèque...'),
                    Forms\Components\Textarea::make('observations')
                        ->label('Observations')
                        ->rows(2),
                ])
                ->action(function (array $data) {
                    $this->encaisserPaiement($data);
                }),

            // Bouton "Réglé par la patronne"
            Actions\Action::make('regle_patronne')
                ->label('Réglé patronne')
                ->icon('heroicon-o-shield-check')
                ->color('info')
                ->requiresConfirmation()
                ->modalDescription('Le solde restant sera marqué comme "réglé par la patronne". Continuer ?')
                ->visible(fn() => ! $this->record->annulee
                    && in_array($this->record->statut_paiement, [StatutPaiement::CREDIT, StatutPaiement::PARTIEL])
                )
                ->action(function () {
                    $this->record->update([
                        'statut_paiement' => StatutPaiement::REGLE_PATRONNE->value,
                        'montant_restant' => 0,
                        'date_reglement_complet' => now()->toDateString(),
                    ]);
                    Notification::make()
                        ->title('Vente réglée')
                        ->body('Marquée comme réglée par la patronne.')
                        ->success()
                        ->send();
                }),

            // Bouton "Annuler la vente"
            Actions\Action::make('annuler_vente')
                ->label('Annuler la vente')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Annuler cette vente')
                ->modalDescription('Le stock sera remis en place et la vente sera marquée comme annulée. Cette action est irréversible.')
                ->form([
                    Forms\Components\Textarea::make('motif_annulation')
                        ->label('Motif d\'annulation')
                        ->required()
                        ->rows(2),
                ])
                ->visible(fn() => ! $this->record->annulee)
                ->action(function (array $data) {
                    $this->annulerVente($data['motif_annulation']);
                }),
        ];
    }

    /**
     * Encaisser un paiement partiel ou total sur une vente à crédit.
     */
    protected function encaisserPaiement(array $data): void
    {
        $vente = $this->record;
        $montant = (float) $data['montant'];
        $restant = (float) $vente->montant_restant;

        // Ne pas encaisser plus que le restant
        if ($montant > $restant) {
            $montant = $restant;
        }

        // Créer le paiement
        Paiement::create([
            'vente_id' => $vente->id,
            'date_paiement' => now(),
            'montant' => $montant,
            'mode_paiement' => $data['mode_paiement'],
            'reference_paiement' => $data['reference_paiement'] ?? null,
            'observations' => $data['observations'] ?? null,
            'created_by' => auth()->id(),
        ]);

        // Mettre à jour la vente
        $nouveauRecu = (float) $vente->montant_recu + $montant;
        $nouveauRestant = max(0, (float) $vente->montant_net - $nouveauRecu);

        $statut = $nouveauRestant <= 0
            ? StatutPaiement::PAYE->value
            : StatutPaiement::PARTIEL->value;

        $vente->update([
            'montant_recu' => round($nouveauRecu, 2),
            'montant_restant' => round($nouveauRestant, 2),
            'statut_paiement' => $statut,
            'date_reglement_complet' => $nouveauRestant <= 0 ? now()->toDateString() : null,
        ]);

        Notification::make()
            ->title('Paiement encaissé')
            ->body(number_format($montant, 0, ',', ' ') . ' FCFA encaissé(s). '
                . ($nouveauRestant > 0 ? 'Reste : ' . number_format($nouveauRestant, 0, ',', ' ') . ' FCFA' : 'Vente réglée !'))
            ->success()
            ->send();
    }

    /**
     * Annuler la vente : remet le stock en place via des mouvements inverses.
     *
     * L'ensemble de l'opération est dans une transaction DB :
     * soit tout réussit (reverse stock + marquage annulée), soit rien.
     */
    protected function annulerVente(string $motif): void
    {
        $vente = $this->record;

        try {
            DB::transaction(function () use ($vente, $motif) {
                $stockService = app(StockService::class);

                // Remettre le stock pour chaque ligne de vente
                foreach ($vente->lignes()->with('produit')->get() as $ligne) {
                    $stockService->entree(
                        produitId: $ligne->produit_id,
                        emplacementId: $vente->emplacement_id,
                        quantite: (float) $ligne->quantite,
                        coutUnitaire: (float) $ligne->cout_revient,
                        typeMouvement: TypeMouvement::ENTREE_ANNULATION,
                        venteId: $vente->id,
                        motif: "Annulation vente {$vente->numero_recu} : {$motif}",
                    );
                }

                // Marquer la vente comme annulée
                $vente->update([
                    'annulee' => true,
                    'date_annulation' => now(),
                    'motif_annulation' => $motif,
                ]);
            });

            Notification::make()
                ->title('Vente annulée')
                ->body("La vente {$vente->numero_recu} a été annulée et le stock a été remis en place.")
                ->warning()
                ->send();
        } catch (\RuntimeException $e) {
            Log::error("Échec annulation vente {$vente->numero_recu}", ['error' => $e->getMessage()]);

            Notification::make()
                ->title('Erreur lors de l\'annulation')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();
        }
    }
}
