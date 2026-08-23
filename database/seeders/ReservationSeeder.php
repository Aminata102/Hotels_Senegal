<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Reservation;
use App\Models\Chambre;
use App\Models\User;

class ReservationSeeder extends Seeder
{
    public function run(): void
    {
        // On récupère le premier utilisateur et la première chambre pour le test
        $user = User::first() ?? User::factory()->create();
        $chambre = Chambre::where('statut', 'Libre')->first();

        if ($chambre) {
            Reservation::create([
                'user_id' => $user->id,
                'chambre_id' => $chambre->id,
                'nom_client' => 'Mamadou Diop',
                'telephone_client' => '771234567',
                'date_arrivee' => now(),
                'date_depart' => now()->addDays(3),
                'montant_total' => $chambre->prix_nuitee * 3,
                'statut' => 'Confirmée',
                'statut_paiement' => 'En attente'
            ]);
        }
    }
}
