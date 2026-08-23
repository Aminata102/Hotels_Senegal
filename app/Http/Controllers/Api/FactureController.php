<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Facture;
use App\Models\Reservation;
use Illuminate\Http\Request;

class FactureController extends Controller
{
    /**
     * Vérifie les permissions d'accès au module Facturation.
     * Administrateur, Caissier et Réceptionniste y ont accès.
     */
    private function verifierPermission(Request $request)
    {
        $user = $request->user();

        if (!$user || (!$user->isAdmin() && !$user->canAccessCaisse() && !$user->canAccessReception())) {
            abort(403, "Vous n'avez pas la permission de gérer les factures.");
        }
    }

    /**
     * Liste des factures.
     * L'Admin voit tout, les autres rôles peuvent voir la totalité ou uniquement leurs factures.
     */
    public function index(Request $request)
    {
        $this->verifierPermission($request);

        $user = $request->user();

        $query = Facture::with(['reservation.chambre', 'reservation.user', 'user']);

        // Si ce n'est pas un Admin, on peut restreindre par hôtel ou par créateur si souhaité
        if (!$user->isAdmin()) {
            // Optionnel : $query->where('user_id', $user->id);
        }

        $factures = $query->latest()->get();

        return response()->json($factures);
    }

    /**
     * Génération d'une nouvelle facture à partir d'une réservation.
     */
    public function store(Request $request)
    {
        $this->verifierPermission($request);

        $validated = $request->validate([
            'reservation_id' => 'required|exists:reservations,id',
            'montant_ht'     => 'required|numeric|min:0',
            'tva'            => 'nullable|numeric|min:0',
            'montant_ttc'    => 'required|numeric|min:0',
            'mode_paiement'  => 'required|string',
            'statut'         => 'nullable|string', // Payée, En attente, Annulée
            'notes'          => 'nullable|string',
        ]);

        // Génération d'un numéro de facture unique (ex: FAC-20260813-001)
        $numeroFacture = 'FAC-' . date('Ymd') . '-' . str_pad(Facture::whereDate('created_at', today())->count() + 1, 3, '0', STR_PAD_LEFT);

        $facture = Facture::create([
            'numero'         => $numeroFacture,
            'user_id'        => $request->user()->id, // Traité par l'Admin ou le Caissier
            'reservation_id' => $validated['reservation_id'],
            'montant_ht'     => $validated['montant_ht'],
            'tva'            => $validated['tva'] ?? 0,
            'montant_ttc'    => $validated['montant_ttc'],
            'mode_paiement'  => $validated['mode_paiement'],
            'statut'         => $validated['statut'] ?? 'Payée',
            'notes'          => $validated['notes'] ?? null,
        ]);

        return response()->json([
            'message' => 'Facture générée avec succès !',
            'facture' => $facture->load(['reservation.chambre', 'user']),
        ], 201);
    }

    /**
     * Détails d'une facture spécifique.
     */
    public function show(Request $request, string $id)
    {
        $this->verifierPermission($request);

        $facture = Facture::with(['reservation.chambre', 'reservation.user', 'user'])->findOrFail($id);

        return response()->json($facture);
    }

    /**
     * Mise à jour d'une facture.
     */
    public function update(Request $request, string $id)
    {
        $this->verifierPermission($request);

        $facture = Facture::findOrFail($id);

        $validated = $request->validate([
            'statut'        => 'sometimes|string',
            'mode_paiement' => 'sometimes|string',
            'montant_ht'    => 'sometimes|numeric',
            'tva'           => 'sometimes|numeric',
            'montant_ttc'   => 'sometimes|numeric',
            'notes'         => 'nullable|string',
        ]);

        $facture->update($validated);

        return response()->json([
            'message' => 'Facture mise à jour avec succès !',
            'facture' => $facture->load(['reservation.chambre', 'user']),
        ]);
    }

    /**
     * Suppression / Annulation d'une facture.
     * Reservé à l'Admin ou au créateur initial de la facture.
     */
    public function destroy(Request $request, string $id)
    {
        $user = $request->user();
        $facture = Facture::findOrFail($id);

        // Seul l'Admin ou l'utilisateur ayant créé la facture peut la supprimer
        if (!$user->isAdmin() && $facture->user_id !== $user->id) {
            return response()->json([
                'message' => 'Seul un Administrateur peut supprimer cette facture.'
            ], 403);
        }

        $facture->delete();

        return response()->json([
            'message' => 'Facture supprimée avec succès !'
        ]);
    }
}
