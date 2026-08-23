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
            Schema::table('reservations', function (Blueprint $table) {
                $table->string('cni_client')->nullable()->after('telephone_client');
            });
        }

        public function down(): void
        {
            Schema::table('reservations', function (Blueprint $table) {
                $table->dropColumn('cni_client');
            });
        }
    };
