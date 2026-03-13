<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Caisse;
use App\Models\Charge;
use App\Models\EauProduction;
use App\Models\Inventaire;
use App\Models\Lot;
use App\Models\Paiement;
use App\Models\Production;
use App\Models\Transfert;
use App\Models\Vente;
use App\Observers\CaisseObserver;
use App\Observers\ChargeObserver;
use App\Observers\EauProductionObserver;
use App\Observers\InventaireObserver;
use App\Observers\LotObserver;
use App\Observers\PaiementObserver;
use App\Observers\ProductionObserver;
use App\Observers\TransfertObserver;
use App\Observers\VenteObserver;
use App\Listeners\LogSuccessfulLogin;
use App\Listeners\LogSuccessfulLogout;
use Filament\Support\View\Components\Modal;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Desactive l'autofocus des modales Filament pour eviter
        // que la modale s'ouvre scrollee vers le bas (Alpine x-trap
        // focus le dernier element focusable et provoque un scroll).
        Modal::autofocus(false);

        Lot::observe(LotObserver::class);
        Production::observe(ProductionObserver::class);
        Transfert::observe(TransfertObserver::class);
        Inventaire::observe(InventaireObserver::class);
        Vente::observe(VenteObserver::class);
        Caisse::observe(CaisseObserver::class);
        Charge::observe(ChargeObserver::class);
        Paiement::observe(PaiementObserver::class);
        EauProduction::observe(EauProductionObserver::class);

        // Listeners pour enregistrer les connexions/déconnexions dans journal_audit
        Event::listen(Login::class, LogSuccessfulLogin::class);
        Event::listen(Logout::class, LogSuccessfulLogout::class);
    }
}
