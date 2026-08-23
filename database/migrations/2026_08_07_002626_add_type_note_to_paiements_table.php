<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paiements', function (Blueprint $table) {
            // 'paiement' = encaissement normal, 'remboursement' = argent rendu au client
            $table->string('type')->default('paiement')->after('methode_paiement');

            // Numéro de facture généré automatiquement (ex: FAC-20260806-0001)
            $table->string('numero_facture')->nullable()->unique()->after('type');

            // Note libre du caissier (raison du remboursement, précision, etc.)
            $table->text('note')->nullable()->after('numero_facture');
        });
    }

    public function down(): void
    {
        Schema::table('paiements', function (Blueprint $table) {
            $table->dropColumn(['type', 'numero_facture', 'note']);
        });
    }
};
