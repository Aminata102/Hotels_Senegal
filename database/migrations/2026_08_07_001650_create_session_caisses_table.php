<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sessions_caisse', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained(); // le caissier qui ouvre/ferme

            $table->timestamp('heure_ouverture');
            $table->timestamp('heure_fermeture')->nullable();

            $table->decimal('fond_ouverture', 12, 2)->default(0); // argent en caisse au démarrage
            $table->decimal('montant_theorique', 12, 2)->nullable(); // calculé = somme des paiements de la session
            $table->decimal('montant_reel', 12, 2)->nullable();      // compté physiquement par le caissier
            $table->decimal('ecart', 12, 2)->nullable();             // montant_reel - montant_theorique

            $table->string('statut')->default('ouverte'); // ouverte | fermée
            $table->text('note')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions_caisse');
    }
};
