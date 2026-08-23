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
       Schema::create('chambres', function (Blueprint $table) {
        $table->id();
        $table->string('numero')->unique();
        $table->enum('type', ['Simple', 'Double', 'Suite', 'Familiale']);
        $table->decimal('prix_nuitee', 12, 2); // Adapté pour les montants en FCFA
        $table->string('statut')->default('Libre'); // Libre, Occupée, Nettoyage
        $table->text('description')->nullable();
        $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chambres');
    }
};
