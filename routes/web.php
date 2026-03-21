<?php

use App\Models\Vente;
use Illuminate\Support\Facades\Route;
use Mpdf\Mpdf;

Route::get('/', function () {
    return view('welcome');
});

// Route pour générer le reçu PDF d'une vente (format ticket de caisse 80mm)
// Utilise mPDF : plus rapide que DomPDF, meilleur support CSS, pas de blocage sur polices
Route::get('/admin/ventes/{vente}/recu-pdf', function (Vente $vente) {
    $vente->load(['client', 'commercial', 'emplacement', 'lignes.produit', 'createdBy']);

    // Rendu du template Blade en HTML
    $html = view('pdf.recu-vente', compact('vente'))->render();

    // Configuration mPDF pour ticket de caisse 80mm
    $mpdf = new Mpdf([
        'format'        => [80, 220],   // largeur 80mm × hauteur 220mm (s'adapte au contenu)
        'margin_top'    => 3,
        'margin_bottom' => 3,
        'margin_left'   => 3,
        'margin_right'  => 3,
        'default_font'  => 'dejavusans', // police incluse dans mPDF, pas besoin de téléchargement
    ]);

    $mpdf->WriteHTML($html);

    // Streaming inline dans le navigateur (pas de téléchargement forcé)
    return response($mpdf->Output('', 'S'), 200, [
        'Content-Type'        => 'application/pdf',
        'Content-Disposition' => 'inline; filename="recu-' . $vente->numero_recu . '.pdf"',
    ]);
})->middleware(['auth'])->name('vente.recu-pdf');
