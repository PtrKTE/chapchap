<?php

declare(strict_types=1);

namespace App\Filament\Resources\ClientResource\Pages;

use App\Exports\ClientsExport;
use App\Filament\Resources\ClientResource;
use App\Imports\ClientsImport;
use App\Models\Client;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Facades\Excel;

class ListClients extends ListRecords
{
    protected static string $resource = ClientResource::class;

    public function getTabs(): array
    {
        return [
            'tous' => Tab::make('Tous')
                ->badge(Client::count()),

            'actifs' => Tab::make('Actifs')
                ->badge(Client::where('actif', true)->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('actif', true)),

            'impayes' => Tab::make('Avec impayés')
                ->badge(Client::whereHas('ventes', fn ($q) =>
                    $q->where('annulee', false)->where('montant_restant', '>', 0)
                )->count())
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $q) => $q->whereHas('ventes', fn ($sq) =>
                    $sq->where('annulee', false)->where('montant_restant', '>', 0)
                )),

            'inactifs' => Tab::make('Inactifs')
                ->badge(Client::where('actif', false)->count())
                ->badgeColor('gray')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('actif', false)),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ClientResource\Widgets\ClientStatsWidget::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),

            // Export Excel
            Actions\Action::make('export_excel')
                ->label('Exporter')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(fn() => Excel::download(
                    new ClientsExport(),
                    'clients_' . now()->format('Y-m-d') . '.xlsx'
                )),

            // Télécharger le modèle Excel pour l'import
            Actions\Action::make('modele_import')
                ->label('Modèle import')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->action(function () {
                    // Génère un fichier Excel avec les colonnes attendues + 2 exemples
                    return \Maatwebsite\Excel\Facades\Excel::download(
                        new \App\Exports\ClientsImportTemplateExport(),
                        'modele_import_clients.xlsx'
                    );
                }),

            // Import Excel
            Actions\Action::make('import_excel')
                ->label('Importer')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('warning')
                ->form([
                    Forms\Components\FileUpload::make('fichier')
                        ->label('Fichier Excel')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                            'text/csv',
                        ])
                        ->required()
                        ->helperText('Colonnes attendues : nom, telephone, email, quartier_zone, type_client, contact_principal, notes'),
                ])
                ->action(function (array $data) {
                    $import = new ClientsImport();
                    Excel::import($import, storage_path('app/public/' . $data['fichier']));

                    $nbImportes = $import->getImportes();
                    $erreurs = $import->getErreurs();

                    $message = "{$nbImportes} client(s) importé(s).";
                    if (count($erreurs) > 0) {
                        $message .= ' ' . count($erreurs) . ' ligne(s) ignorée(s).';
                    }

                    Notification::make()
                        ->title('Import terminé')
                        ->body($message)
                        ->success()
                        ->send();
                }),
        ];
    }
}
