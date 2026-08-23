<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained();
            $table->string('nom_client');
            $table->string('telephone_client');
            $table->foreignId('chambre_id')->constrained();
            $table->date('date_arrivee');
            $table->date('date_depart');
            $table->decimal('montant_total', 12, 2);

            // Un seul statut de paiement
            $table->string('statut_paiement')->default('En attente');

            // Un seul statut de réservation (Enum ou String simple)
            $table->enum('statut', ['En attente', 'Confirmée', 'Annulée', 'Terminée'])->default('En attente');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
