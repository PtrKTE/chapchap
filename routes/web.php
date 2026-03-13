<?php

use App\Models\Vente;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Route pour générer le reçu PDF d'une vente (format ticket de caisse)
Route::get('/admin/ventes/{vente}/recu-pdf', function (Vente $vente) {
    $vente->load(['client', 'commercial', 'emplacement', 'lignes.produit', 'createdBy']);

    $pdf = Pdf::loadView('pdf.recu-vente', compact('vente'))
        ->setPaper([0, 0, 226.77, 800], 'portrait'); // 80mm de large (ticket de caisse)

    return $pdf->stream("recu-{$vente->numero_recu}.pdf");
})->middleware(['auth'])->name('vente.recu-pdf');
