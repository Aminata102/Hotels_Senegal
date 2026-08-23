<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('prenom', 100);
            $table->string('nom', 100);
            $table->string('telephone', 20);
            $table->string('email', 150)->nullable()->unique();
            $table->string('numero_cni', 50)->nullable();
            $table->string('nationalite', 100)->default('Sénégalaise');
            $table->string('ville', 100)->nullable();
            $table->enum('statut', ['en_sejour', 'recent', 'checkout'])->default('recent');
            $table->unsignedInteger('nombre_sejours')->default(0);
            $table->enum('statut_paiement', ['paye', 'du', 'en_attente'])->default('en_attente');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
