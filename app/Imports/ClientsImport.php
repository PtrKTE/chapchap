<?php

declare(strict_types=1);

namespace App\Imports;

use App\Models\Client;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Validators\Failure;

/**
 * Import Excel de clients.
 *
 * Colonnes attendues (en-têtes de la première ligne) :
 * nom | telephone | email | quartier_zone | type_client | contact_principal | notes
 *
 * Le code client est auto-généré (CLI-XXXX).
 * Les lignes avec erreur sont ignorées (SkipsOnFailure).
 */
class ClientsImport implements ToModel, WithHeadingRow, WithValidation, SkipsEmptyRows, SkipsOnFailure, SkipsOnError
{
    use Importable;

    private int $importes = 0;
    private array $erreurs = [];

    public function model(array $row): ?Client
    {
        // Générer le code client automatiquement
        $dernier = Client::where('code', 'like', 'CLI-%')
            ->orderByDesc('code')
            ->value('code');
        $numero = $dernier
            ? ((int) Str::afterLast($dernier, '-')) + 1
            : 1;
        $code = 'CLI-' . str_pad((string) $numero, 4, '0', STR_PAD_LEFT);

        $this->importes++;

        return new Client([
            'code' => $code,
            'nom' => $row['nom'],
            'telephone' => $row['telephone'] ?? null,
            'email' => $row['email'] ?? null,
            'quartier_zone' => $row['quartier_zone'] ?? null,
            'type_client' => $row['type_client'] ?? 'autre',
            'contact_principal' => $row['contact_principal'] ?? null,
            'notes' => $row['notes'] ?? null,
            'date_enregistrement' => now()->toDateString(),
            'actif' => true,
        ]);
    }

    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:150'],
            'telephone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:150'],
            'type_client' => ['nullable', 'string'],
        ];
    }

    public function onFailure(Failure ...$failures): void
    {
        foreach ($failures as $failure) {
            $this->erreurs[] = "Ligne {$failure->row()}: " . implode(', ', $failure->errors());
        }
    }

    public function onError(\Throwable $e): void
    {
        $this->erreurs[] = $e->getMessage();
    }

    public function getImportes(): int
    {
        return $this->importes;
    }

    public function getErreurs(): array
    {
        return $this->erreurs;
    }
}
