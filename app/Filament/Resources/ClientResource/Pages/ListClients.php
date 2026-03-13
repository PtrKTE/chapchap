<?php

declare(strict_types=1);

namespace App\Filament\Resources\ClientResource\Pages;

use App\Exports\ClientsExport;
use App\Filament\Resources\ClientResource;
use App\Imports\ClientsImport;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Maatwebsite\Excel\Facades\Excel;

class ListClients extends ListRecords
{
    protected static string $resource = ClientResource::class;

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
