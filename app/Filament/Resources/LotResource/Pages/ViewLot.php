<?php

declare(strict_types=1);

namespace App\Filament\Resources\LotResource\Pages;

use App\Enums\ModePaiement;
use App\Enums\StatutFacture;
use App\Filament\Resources\LotResource;
use App\Models\Lot;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewLot extends ViewRecord
{
    protected static string $resource = LotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Bouton "Modifier"
            Actions\EditAction::make(),

            // Action "Enregistrer un règlement" — visible si facture non totalement réglée
            Actions\Action::make('reglement')
                ->label('Enregistrer un règlement')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->visible(fn (Lot $record): bool => $record->statut_facture !== StatutFacture::REGLEE)
                ->form([
                    Forms\Components\TextInput::make('montant')
                        ->label('Montant réglé (FCFA)')
                        ->numeric()
                        ->required()
                        ->minValue(1)
                        ->suffix('FCFA')
                        ->helperText(fn () => 'Restant : ' . number_format(
                            max(0, (float) $this->record->montant_facture - (float) $this->record->montant_regle),
                            0, ',', ' '
                        ) . ' FCFA'),
                    Forms\Components\Select::make('mode_reglement')
                        ->label('Mode de paiement')
                        ->options(collect(ModePaiement::cases())->mapWithKeys(fn ($e) => [$e->value => $e->getLabel()]))
                        ->required(),
                    Forms\Components\DatePicker::make('date_reglement')
                        ->label('Date du règlement')
                        ->default(now())
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->required(),
                ])
                ->action(function (Lot $record, array $data): void {
                    $montantVerse = (float) $data['montant'];
                    $nouveauMontantRegle = (float) $record->montant_regle + $montantVerse;
                    $montantFacture = (float) $record->montant_facture;

                    // Calculer le nouveau statut
                    if ($nouveauMontantRegle <= 0) {
                        $statut = StatutFacture::NON_REGLEE;
                    } elseif ($nouveauMontantRegle < $montantFacture) {
                        $statut = StatutFacture::PARTIELLE;
                    } else {
                        $statut = StatutFacture::REGLEE;
                    }

                    $record->update([
                        'montant_regle'  => round($nouveauMontantRegle, 2),
                        'statut_facture' => $statut->value,
                        'mode_reglement' => $data['mode_reglement'],
                        'date_reglement' => $statut === StatutFacture::REGLEE
                            ? $data['date_reglement']
                            : $record->date_reglement,
                    ]);

                    Notification::make()
                        ->title('Règlement enregistré')
                        ->body(number_format($montantVerse, 0, ',', ' ') . ' FCFA enregistrés. Statut : ' . $statut->getLabel())
                        ->success()
                        ->send();
                }),
        ];
    }
}
