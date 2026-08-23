<?php

namespace App\Http\Controllers;

use App\Models\SessionCaisse;
use App\Models\Paiement;
use Illuminate\Http\Request;

class CaisseController extends Controller
{
    // GET /api/caisse/statut — renvoie la session ouverte actuelle (s'il y en a une) pour l'utilisateur connecté
    public function statutActuelle(Request $request)
    {
        $session = SessionCaisse::where('user_id', $request->user()->id)
            ->where('statut', 'ouverte')
            ->latest('heure_ouverture')
            ->first();

        if (!$session) {
            return response()->json(['ouverte' => false]);
        }

        // Calcule le montant théorique en direct (paiements encaissés depuis l'ouverture)
        $totalPaiements = Paiement::where('user_id', $request->user()->id)
            ->where('type', 'paiement')
            ->where('created_at', '>=', $session->heure_ouverture)
            ->sum('montant');

        $totalRemboursements = Paiement::where('user_id', $request->user()->id)
            ->where('type', 'remboursement')
            ->where('created_at', '>=', $session->heure_ouverture)
            ->sum('montant');

        return response()->json([
            'ouverte'            => true,
            'session'            => $session,
            'montant_theorique'  => $session->fond_ouverture + $totalPaiements - $totalRemboursements,
            'total_encaisse'     => $totalPaiements,
            'total_rembourse'    => $totalRemboursements,
        ]);
    }

    // POST /api/caisse/ouvrir — ouvre une nouvelle session de caisse
    public function ouvrir(Request $request)
    {
        $request->validate([
            'fond_ouverture' => 'required|numeric|min:0',
        ]);

        $dejaOuverte = SessionCaisse::where('user_id', $request->user()->id)
            ->where('statut', 'ouverte')
            ->exists();

        if ($dejaOuverte) {
            return response()->json(['message' => 'Une session de caisse est déjà ouverte.'], 422);
        }

        $session = SessionCaisse::create([
            'user_id'         => $request->user()->id,
            'heure_ouverture' => now(),
            'fond_ouverture'  => $request->fond_ouverture,
            'statut'          => 'ouverte',
        ]);

        return response()->json([
            'message' => 'Caisse ouverte avec succès !',
            'session' => $session,
        ], 201);
    }

    // POST /api/caisse/fermer — ferme la session en cours et calcule l'écart
    public function fermer(Request $request)
    {
        $request->validate([
            'montant_reel' => 'required|numeric|min:0',
            'note'         => 'nullable|string',
        ]);

        $session = SessionCaisse::where('user_id', $request->user()->id)
            ->where('statut', 'ouverte')
            ->latest('heure_ouverture')
            ->first();

        if (!$session) {
            return response()->json(['message' => 'Aucune session de caisse ouverte à fermer.'], 422);
        }

        $totalPaiements = Paiement::where('user_id', $request->user()->id)
            ->where('type', 'paiement')
            ->where('created_at', '>=', $session->heure_ouverture)
            ->sum('montant');

        $totalRemboursements = Paiement::where('user_id', $request->user()->id)
            ->where('type', 'remboursement')
            ->where('created_at', '>=', $session->heure_ouverture)
            ->sum('montant');

        $montantTheorique = $session->fond_ouverture + $totalPaiements - $totalRemboursements;
        $ecart = $request->montant_reel - $montantTheorique;

        $session->update([
            'heure_fermeture'    => now(),
            'montant_theorique'  => $montantTheorique,
            'montant_reel'       => $request->montant_reel,
            'ecart'              => $ecart,
            'statut'             => 'fermée',
            'note'               => $request->note,
        ]);

        return response()->json([
            'message' => 'Caisse clôturée avec succès !',
            'session' => $session,
        ]);
    }

    // GET /api/caisse/historique — liste des sessions passées (avec pagination)
    public function historique(Request $request)
    {
        $query = SessionCaisse::with('user')->orderByDesc('heure_ouverture');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        return response()->json($query->paginate(30));
    }
}
