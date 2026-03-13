<x-filament-panels::page>

    {{-- Widget de statistiques (rendu manuellement, pas sur le dashboard) --}}
    @livewire(\App\Filament\Widgets\AuditStatsWidget::class)

    {{-- Navigation par onglets --}}
    <div class="flex gap-2">
        <x-filament::button
            :color="$activeTab === 'journal' ? 'primary' : 'gray'"
            wire:click="$set('activeTab', 'journal')"
            icon="heroicon-o-document-text"
            size="sm"
        >
            Journal d'audit
        </x-filament::button>

        <x-filament::button
            :color="$activeTab === 'en_ligne' ? 'primary' : 'gray'"
            wire:click="$set('activeTab', 'en_ligne')"
            icon="heroicon-o-signal"
            size="sm"
        >
            Utilisateurs en ligne
        </x-filament::button>
    </div>

    {{-- Table (contenu change selon l'onglet actif) --}}
    {{ $this->table }}

</x-filament-panels::page>
