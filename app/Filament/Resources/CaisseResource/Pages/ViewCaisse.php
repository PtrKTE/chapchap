<?php

declare(strict_types=1);

namespace App\Filament\Resources\CaisseResource\Pages;

use App\Enums\StatutCaisse;
use App\Filament\Resources\CaisseResource;
use App\Services\CaisseService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;

/**
 * Page de consultation d'une caisse journalière.
 *
 * Actions disponibles selon le statut :
 * - OUVERTE  → Recalculer, Clôturer, Imprimer rapport
 * - CLOTUREE → Valider (gérant), Imprimer rapport
 * - VALIDEE  → Imprimer rapport uniquement
 */
class ViewCaisse extends ViewRecord
{
    protected static string $resource = CaisseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Modifier manuellement
            Actions\EditAction::make()
                ->label('Modifier')
                ->icon('heroicon-o-pencil')
                ->visible(fn () => $this->record->statut !== StatutCaisse::VALIDEE),

            // Recalculer depuis les ventes et charges du jour
            Actions\Action::make('recalculer')
                ->label('Recalculer')
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Recalculer la caisse')
                ->modalDescription('Les totaux seront recalculés à partir des ventes et charges enregistrées pour ce jour. Continuer ?')
                ->visible(fn () => $this->record->statut === StatutCaisse::OUVERTE)
                ->action(function () {
                    $service = app(CaisseService::class);
                    $service->recalculer($this->record);

                    Notification::make()
                        ->title('Caisse recalculée')
                        ->body('Les totaux ont été mis à jour depuis les ventes et charges du jour.')
                        ->success()
                        ->send();

                    $this->refreshFormData([
                        'total_encaisse', 'total_credits_encaisses',
                        'total_decaisse', 'solde_caisse', 'ecart',
                    ]);
                }),

            // Clôturer la caisse (fin de journée)
            Actions\Action::make('cloturer')
                ->label('Clôturer la caisse')
                ->icon('heroicon-o-lock-closed')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Clôturer cette caisse')
                ->modalDescription('La caisse sera recalculée puis clôturée. Elle ne pourra plus être modifiée sans validation du gérant. Continuer ?')
                ->visible(fn () => $this->record->statut === StatutCaisse::OUVERTE)
                ->action(function () {
                    $service = app(CaisseService::class);

                    try {
                        $service->cloturer($this->record);

                        Notification::make()
                            ->title('Caisse clôturée')
                            ->body('La caisse a été clôturée avec succès. En attente de validation.')
                            ->success()
                            ->send();

                        $this->refreshFormData(['statut', 'total_encaisse', 'solde_caisse', 'ecart']);
                    } catch (\RuntimeException $e) {
                        Notification::make()
                            ->title('Impossible de clôturer')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            // Valider la caisse (réservé gérant/resp_operations)
            Actions\Action::make('valider')
                ->label('Valider')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Valider cette caisse')
                ->modalDescription('La caisse clôturée sera définitivement validée. Cette action est irréversible.')
                ->visible(fn () => $this->record->statut === StatutCaisse::CLOTUREE)
                ->action(function () {
                    $service = app(CaisseService::class);

                    try {
                        $service->valider($this->record, auth()->id());

                        Notification::make()
                            ->title('Caisse validée')
                            ->body('La caisse a été validée avec succès.')
                            ->success()
                            ->send();

                        $this->refreshFormData(['statut']);
                    } catch (\RuntimeException $e) {
                        Notification::make()
                            ->title('Impossible de valider')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            // Imprimer le rapport de caisse PDF
            Actions\Action::make('imprimer_rapport')
                ->label('Rapport PDF')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->action(function () {
                    $caisse = $this->record->load([
                        'emplacement', 'responsable', 'validePar',
                    ]);

                    // Charger les charges du jour pour les afficher dans le rapport
                    $charges = \App\Models\Charge::with('categorie')
                        ->where('emplacement_id', $caisse->emplacement_id)
                        ->whereDate('date_charge', $caisse->date_caisse)
                        ->orderBy('categorie_id')
                        ->get();

                    $html = view('pdf.rapport-caisse', ['caisse' => $caisse, 'charges' => $charges])->render();

                    // Dossiers dans storage/ pour contourner open_basedir Hestia
                    $tmpDir  = storage_path('app/mpdf-tmp');
                    $fontDir = storage_path('fonts/mpdf');

                    if (! is_dir($tmpDir)) {
                        mkdir($tmpDir, 0755, true);
                    }
                    if (! is_dir($fontDir)) {
                        mkdir($fontDir, 0755, true);
                    }

                    $defaultConfig = (new ConfigVariables())->getDefaults();
                    $defaultFontConfig = (new FontVariables())->getDefaults();

                    $mpdf = new Mpdf([
                        'format'       => 'A4',
                        'margin_top'   => 15,
                        'margin_bottom'=> 15,
                        'margin_left'  => 20,
                        'margin_right' => 20,
                        'default_font' => 'dejavusans',
                        'tempDir'      => $tmpDir,
                        'fontDir'      => array_merge($defaultConfig['fontDir'], [$fontDir]),
                        'fontdata'     => $defaultFontConfig['fontdata'],
                    ]);

                    $mpdf->WriteHTML($html);

                    $filename = 'rapport-caisse-' . $caisse->date_caisse->format('Y-m-d') . '.pdf';

                    return response()->streamDownload(
                        fn () => print($mpdf->Output('', 'S')),
                        $filename,
                        ['Content-Type' => 'application/pdf']
                    );
                }),
        ];
    }
}
