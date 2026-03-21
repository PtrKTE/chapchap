<?php

use App\Models\Vente;
use Illuminate\Support\Facades\Route;
use Mpdf\Mpdf;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;

Route::get('/', function () {
    return view('welcome');
});

// Route pour générer le reçu PDF d'une vente (format ticket de caisse 80mm)
// mPDF configuré avec des chemins dans storage/ pour contourner open_basedir Hestia
Route::get('/admin/ventes/{vente}/recu-pdf', function (Vente $vente) {
    $vente->load(['client', 'commercial', 'emplacement', 'lignes.produit', 'createdBy']);

    // Rendu du template Blade en HTML
    $html = view('pdf.recu-vente', compact('vente'))->render();

    // Répertoires dans storage/ — autorisés par open_basedir Hestia
    $tmpDir  = storage_path('app/mpdf-tmp');
    $fontDir = storage_path('fonts/mpdf');

    // Créer les dossiers s'ils n'existent pas encore sur le serveur
    if (! is_dir($tmpDir)) {
        mkdir($tmpDir, 0755, true);
    }
    if (! is_dir($fontDir)) {
        mkdir($fontDir, 0755, true);
    }

    // Récupérer la config mPDF par défaut et ajouter notre dossier de polices
    $defaultConfig = (new ConfigVariables())->getDefaults();
    $fontDirs = $defaultConfig['fontDir'];

    $defaultFontConfig = (new FontVariables())->getDefaults();
    $fontData = $defaultFontConfig['fontdata'];

    // Configuration mPDF pour ticket de caisse 80mm
    $mpdf = new Mpdf([
        'format'        => [80, 220],
        'margin_top'    => 3,
        'margin_bottom' => 3,
        'margin_left'   => 3,
        'margin_right'  => 3,
        'default_font'  => 'dejavusans',
        'tempDir'       => $tmpDir,   // Évite /tmp (bloqué par open_basedir)
        'fontDir'       => array_merge($fontDirs, [$fontDir]),
        'fontdata'      => $fontData,
    ]);

    $mpdf->WriteHTML($html);

    // Streaming inline dans le navigateur
    return response($mpdf->Output('', 'S'), 200, [
        'Content-Type'        => 'application/pdf',
        'Content-Disposition' => 'inline; filename="recu-' . $vente->numero_recu . '.pdf"',
    ]);
})->middleware(['auth'])->name('vente.recu-pdf');
