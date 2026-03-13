<?php

declare(strict_types=1);

namespace App\Traits;

use Filament\Actions\Action;
use Filament\Forms;

/**
 * Trait pour ajouter un bouton d'export Excel filtrable par dates.
 *
 * Utilisé dans les pages ListRecords de Filament.
 * Crée une action modale avec 2 champs date (début / fin)
 * puis appelle la méthode telechargerExport() qui doit être
 * définie dans la classe qui utilise ce trait.
 */
trait ExportableParDates
{
    /**
     * Crée l'action d'export avec un formulaire modal de filtre par dates.
     */
    protected function actionExportExcel(string $label = 'Exporter Excel'): Action
    {
        return Action::make('export_excel')
            ->label($label)
            ->icon('heroicon-o-arrow-down-tray')
            ->color('success')
            ->form([
                Forms\Components\DatePicker::make('date_debut')
                    ->label('Date début')
                    ->native(false),
                Forms\Components\DatePicker::make('date_fin')
                    ->label('Date fin')
                    ->native(false),
            ])
            ->action(function (array $data) {
                return $this->telechargerExport(
                    $data['date_debut'] ?? null,
                    $data['date_fin'] ?? null,
                );
            });
    }
}
