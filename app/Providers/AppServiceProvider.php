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
use App\Filament\Resources\CaisseResource\Widgets\CaisseStatsWidget;
use App\Filament\Resources\ChargeResource\Widgets\ChargeStatsWidget;
use App\Filament\Resources\ClientResource\Widgets\ClientStatsWidget;
use App\Filament\Resources\EauProductionResource\Widgets\EauStatsWidget;
use App\Filament\Resources\LotResource\Widgets\LotStatsWidget;
use App\Filament\Resources\ProductionResource\Widgets\ProductionStatsWidget;
use App\Filament\Resources\StockEmplacementResource\Widgets\StockStatsWidget;
use App\Filament\Resources\VenteResource\Widgets\VenteStatsWidget;
use Filament\Support\View\Components\Modal;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

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

        // Enregistrement manuel des widgets imbriqués dans les Resources
        // (non découverts automatiquement par discoverWidgets qui ne scanne que app/Filament/Widgets)
        Livewire::component('app.filament.resources.caisse-resource.widgets.caisse-stats-widget', CaisseStatsWidget::class);
        Livewire::component('app.filament.resources.charge-resource.widgets.charge-stats-widget', ChargeStatsWidget::class);
        Livewire::component('app.filament.resources.client-resource.widgets.client-stats-widget', ClientStatsWidget::class);
        Livewire::component('app.filament.resources.eau-production-resource.widgets.eau-stats-widget', EauStatsWidget::class);
        Livewire::component('app.filament.resources.lot-resource.widgets.lot-stats-widget', LotStatsWidget::class);
        Livewire::component('app.filament.resources.production-resource.widgets.production-stats-widget', ProductionStatsWidget::class);
        Livewire::component('app.filament.resources.stock-emplacement-resource.widgets.stock-stats-widget', StockStatsWidget::class);
        Livewire::component('app.filament.resources.vente-resource.widgets.vente-stats-widget', VenteStatsWidget::class);
    }
}
