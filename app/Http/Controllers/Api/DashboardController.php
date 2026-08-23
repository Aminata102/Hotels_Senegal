<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Chambre;
use App\Models\Reservation;
use App\Models\Paiement;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        try {
            $today = Carbon::today();

            // 1. Statistiques des chambres
            $totalChambres = Chambre::count();
            $chambresOccupees = Chambre::where('statut', 'occupee')->count();
            $chambresLibres = Chambre::where('statut', 'libre')->count(); // Ajout du calcul
            $tauxOccupation = $totalChambres > 0 ? round(($chambresOccupees / $totalChambres) * 100) : 0;

            // 2. Recettes du jour (Somme des paiements effectués aujourd'hui)
            $recettesJour = Paiement::whereDate('created_at', $today)->sum('montant');

            // 3. Check-ins et Check-outs du jour
            $checkIns = Reservation::whereDate('date_arrivee', $today)->count();
            $checkOuts = Reservation::whereDate('date_depart', $today)->count();

            // 4. Liste des réservations récentes (pour le flux sur Flutter)
            // On récupère les 5 dernières réservations avec les infos de la chambre
            $recentReservations = Reservation::with('chambre')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'stats' => [
                        'chambres_occupees' => $chambresOccupees,
                        'chambres_libres' => $chambresLibres, // Ajout à la réponse JSON
                        'total_chambres' => $totalChambres,
                        'taux_occupation' => $tauxOccupation,
                        'recettes_jour' => $recettesJour,
                        'check_ins' => $checkIns,
                        'check_outs' => $checkOuts,
                    ],
                    'reservations' => $recentReservations,
                    'alerte' => [
                        'message' => "Un paiement est en attente pour une chambre.",
                        'chambre' => "07"
                    ]
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des données : ' . $e->getMessage()
            ], 500);
        }
    }
}
