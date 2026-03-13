<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes de performance pour les tables à fort trafic.
 *
 * Les foreign keys créent déjà des indexes simples.
 * Cette migration ajoute les indexes sur les colonnes fréquemment
 * filtrées (dates, statuts) et des indexes composites pour les
 * requêtes courantes (dashboards, rapports, filtres).
 */
return new class extends Migration
{
    public function up(): void
    {
        // mouvements_stock — table la plus volumineuse
        Schema::table('mouvements_stock', function (Blueprint $table) {
            $table->index('date_mouvement', 'idx_mvt_date');
            $table->index('type_mouvement', 'idx_mvt_type');
            $table->index(['produit_id', 'emplacement_id', 'date_mouvement'], 'idx_mvt_produit_empl_date');
        });

        // ventes — dashboards et rapports quotidiens
        Schema::table('ventes', function (Blueprint $table) {
            $table->index('date_vente', 'idx_ventes_date');
            $table->index('statut_paiement', 'idx_ventes_statut');
            $table->index(['emplacement_id', 'date_vente'], 'idx_ventes_empl_date');
            $table->index(['commercial_id', 'date_vente'], 'idx_ventes_commercial_date');
        });

        // paiements — suivi des encaissements
        Schema::table('paiements', function (Blueprint $table) {
            $table->index('date_paiement', 'idx_paiements_date');
        });

        // lots — suivi achats et factures
        Schema::table('lots', function (Blueprint $table) {
            $table->index('date_reception', 'idx_lots_date');
            $table->index('statut_facture', 'idx_lots_statut');
        });

        // productions — rapports production
        Schema::table('productions', function (Blueprint $table) {
            $table->index('date_production', 'idx_prod_date');
            $table->index('statut', 'idx_prod_statut');
        });

        // caisses — clôture journalière
        Schema::table('caisses', function (Blueprint $table) {
            $table->index('date_caisse', 'idx_caisses_date');
            $table->index('statut', 'idx_caisses_statut');
        });

        // journal_audit — traçabilité
        Schema::table('journal_audit', function (Blueprint $table) {
            $table->index(['table_concernee', 'enregistrement_id'], 'idx_audit_table_record');
            $table->index('created_at', 'idx_audit_date');
        });
    }

    public function down(): void
    {
        Schema::table('mouvements_stock', function (Blueprint $table) {
            $table->dropIndex('idx_mvt_date');
            $table->dropIndex('idx_mvt_type');
            $table->dropIndex('idx_mvt_produit_empl_date');
        });

        Schema::table('ventes', function (Blueprint $table) {
            $table->dropIndex('idx_ventes_date');
            $table->dropIndex('idx_ventes_statut');
            $table->dropIndex('idx_ventes_empl_date');
            $table->dropIndex('idx_ventes_commercial_date');
        });

        Schema::table('paiements', function (Blueprint $table) {
            $table->dropIndex('idx_paiements_date');
        });

        Schema::table('lots', function (Blueprint $table) {
            $table->dropIndex('idx_lots_date');
            $table->dropIndex('idx_lots_statut');
        });

        Schema::table('productions', function (Blueprint $table) {
            $table->dropIndex('idx_prod_date');
            $table->dropIndex('idx_prod_statut');
        });

        Schema::table('caisses', function (Blueprint $table) {
            $table->dropIndex('idx_caisses_date');
            $table->dropIndex('idx_caisses_statut');
        });

        Schema::table('journal_audit', function (Blueprint $table) {
            $table->dropIndex('idx_audit_table_record');
            $table->dropIndex('idx_audit_date');
        });
    }
};
