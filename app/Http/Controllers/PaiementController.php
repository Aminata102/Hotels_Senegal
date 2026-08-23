<?php

namespace App\Http\Controllers;

use App\Models\Paiement;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaiementController extends Controller
{
    // GET /api/paiements — historique des paiements (avec filtres optionnels)
    public function index(Request $request)
    {
        $query = Paiement::with(['reservation', 'user'])->orderByDesc('created_at');

        // Filtre par date précise (ex: ?date=2026-08-06)
        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->date);
        }

        // Filtre par intervalle de dates
        if ($request->filled('date_debut') && $request->filled('date_fin')) {
            $query->whereBetween('created_at', [$request->date_debut, $request->date_fin]);
        }

        // Filtre par type (paiement / remboursement)
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // Filtre par réservation précise
        if ($request->filled('reservation_id')) {
            $query->where('reservation_id', $request->reservation_id);
        }

        return response()->json($query->paginate(50));
    }

    // GET /api/paiements/{id} — détail d'un paiement (pour afficher/imprimer une facture)
    public function show($id)
    {
        $paiement = Paiement::with(['reservation.chambre', 'user'])->findOrFail($id);
        return response()->json($paiement);
    }

    // POST /api/paiements — enregistrer un paiement (encaissement)
    public function store(Request $request)
    {
        $request->validate([
            'reservation_id'    => 'required|exists:reservations,id',
            'montant'           => 'required|numeric|min:1',
            'methode_paiement'  => 'required|string', // Wave, Orange Money, Cash, Carte
            'note'              => 'nullable|string',
        ]);

        $reservation = Reservation::findOrFail($request->reservation_id);

        // ✅ Empêche d'encaisser plus que ce qui est réellement dû
        $dejaPaye = Paiement::where('reservation_id', $reservation->id)
            ->where('type', 'paiement')
            ->sum('montant');
        $dejaRembourse = Paiement::where('reservation_id', $reservation->id)
            ->where('type', 'remboursement')
            ->sum('montant');
        $solde = $reservation->montant_total - ($dejaPaye - $dejaRembourse);

        if ($request->montant > $solde) {
            return response()->json([
                'message' => "Le montant dépasse le solde restant dû (reste à payer : {$solde} FCFA)."
            ], 422);
        }

        $paiement = Paiement::create([
            'reservation_id'   => $request->reservation_id,
            'user_id'          => $request->user()->id, // ✅ CORRIGÉ — manquait avant, causait un crash
            'montant'          => $request->montant,
            'methode_paiement' => $request->methode_paiement,
            'type'             => 'paiement',
            'numero_facture'   => $this->genererNumeroFacture(),
            'note'             => $request->note,
        ]);

        // Met à jour le statut de paiement de la réservation
        $this->mettreAJourStatutPaiement($reservation);

        return response()->json([
            'message'  => 'Paiement enregistré avec succès !',
            'paiement' => $paiement->load('reservation'),
        ], 201);
    }

    // POST /api/paiements/remboursement — enregistrer un remboursement
    public function storeRemboursement(Request $request)
    {
        $request->validate([
            'reservation_id'   => 'required|exists:reservations,id',
            'montant'          => 'required|numeric|min:1',
            'methode_paiement' => 'required|string',
            'note'             => 'required|string', // motif obligatoire pour un remboursement
        ]);

        $reservation = Reservation::findOrFail($request->reservation_id);

        $dejaPaye = Paiement::where('reservation_id', $reservation->id)
            ->where('type', 'paiement')
            ->sum('montant');
        $dejaRembourse = Paiement::where('reservation_id', $reservation->id)
            ->where('type', 'remboursement')
            ->sum('montant');
        $montantEncaisseNet = $dejaPaye - $dejaRembourse;

        if ($request->montant > $montantEncaisseNet) {
            return response()->json([
                'message' => "Impossible de rembourser plus que ce qui a été encaissé ({$montantEncaisseNet} FCFA)."
            ], 422);
        }

        $remboursement = Paiement::create([
            'reservation_id'   => $request->reservation_id,
            'user_id'          => $request->user()->id,
            'montant'          => $request->montant,
            'methode_paiement' => $request->methode_paiement,
            'type'             => 'remboursement',
            'numero_facture'   => $this->genererNumeroFacture('REMB'),
            'note'             => $request->note,
        ]);

        $this->mettreAJourStatutPaiement($reservation);

        return response()->json([
            'message'       => 'Remboursement enregistré avec succès !',
            'remboursement' => $remboursement->load('reservation'),
        ], 201);
    }

    // GET /api/paiements/stats — recette du jour + répartition par mode de paiement
    public function stats(Request $request)
    {
        $date = $request->filled('date') ? $request->date : now()->toDateString();

        $paiementsJour = Paiement::whereDate('created_at', $date)->where('type', 'paiement');
        $remboursementsJour = Paiement::whereDate('created_at', $date)->where('type', 'remboursement');

        $totalEncaisse = (clone $paiementsJour)->sum('montant');
        $totalRembourse = (clone $remboursementsJour)->sum('montant');

        $repartitionParMode = (clone $paiementsJour)
            ->select('methode_paiement', DB::raw('SUM(montant) as total'))
            ->groupBy('methode_paiement')
            ->get();

        return response()->json([
            'date'                 => $date,
            'total_encaisse'       => $totalEncaisse,
            'total_rembourse'      => $totalRembourse,
            'recette_nette'        => $totalEncaisse - $totalRembourse,
            'nombre_paiements'     => (clone $paiementsJour)->count(),
            'repartition_par_mode' => $repartitionParMode,
        ]);
    }

    // Génère un numéro de facture unique du type FAC-20260806-0001 (ou REMB-...)
    private function genererNumeroFacture(string $prefixe = 'FAC'): string
    {
        $today = now()->format('Ymd');
        $count = Paiement::whereDate('created_at', now()->toDateString())->count() + 1;
        return "{$prefixe}-{$today}-" . str_pad($count, 4, '0', STR_PAD_LEFT);
    }

    // Recalcule le statut de paiement d'une réservation (Non payé / Partiel / Payé)
    private function mettreAJourStatutPaiement(Reservation $reservation): void
    {
        $totalPaye = Paiement::where('reservation_id', $reservation->id)->where('type', 'paiement')->sum('montant');
        $totalRembourse = Paiement::where('reservation_id', $reservation->id)->where('type', 'remboursement')->sum('montant');
        $net = $totalPaye - $totalRembourse;

        if ($net <= 0) {
            $statutPaiement = 'Non payé';
        } elseif ($net < $reservation->montant_total) {
            $statutPaiement = 'Partiel';
        } else {
            $statutPaiement = 'Payé';
        }

        $reservation->update(['statut_paiement' => $statutPaiement]);
    }
}
