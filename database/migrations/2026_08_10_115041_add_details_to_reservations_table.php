<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->string('cni_client')->nullable()->after('telephone_client');
            $table->unsignedInteger('nombre_adultes')->default(1)->after('cni_client');
            $table->unsignedInteger('nombre_enfants')->default(0)->after('nombre_adultes');
            $table->string('mode_paiement')->nullable()->after('nombre_enfants');
            $table->text('note')->nullable()->after('mode_paiement');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn(['cni_client', 'nombre_adultes', 'nombre_enfants', 'mode_paiement', 'note']);
        });
    }
};
