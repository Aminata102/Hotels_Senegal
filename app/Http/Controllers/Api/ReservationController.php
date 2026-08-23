<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use Illuminate\Http\Request;

class ReservationController extends Controller
{
    /**
     * Vérifie les permissions d'écriture (Création, Modification, Suppression).
     * Autorisé : Admin, Gérant, Réceptionniste.
     * Interdit : Caissier / Utilisateurs non habilités.
     */
    private function verifierPermission(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            abort(401, "Utilisateur non authentifié.");
        }

        // Récupération et normalisation du rôle (string ou relation)
        $roleName = strtolower(trim($user->role->name ?? $user->role ?? $user->type ?? ''));

        // Exclusion explicite du Caissier ou rejet des profils non autorisés
        if ($roleName === 'caissier' || (!$user->isAdmin() && !$user->canAccessReception())) {
            abort(403, "Accès refusé : Votre rôle ne vous permet pas de gérer ou modifier les réservations.");
        }
    }

    /**
     * Liste des réservations avec totaux de paiements calculés.
     * Accessible à tous les utilisateurs authentifiés (lecture seule pour Caissier).
     */
    public function index(Request $request)
    {
        $query = Reservation::with(['chambre', 'user'])
            ->withSum(['paiements as total_paye' => function ($q) {
                $q->where('type', 'paiement');
            }], 'montant')
            ->withSum(['paiements as total_rembourse' => function ($q) {
                $q->where('type', 'remboursement');
            }], 'montant');

        $reservations = $query->latest()
            ->get()
            ->map(function ($r) {
                $paye = ($r->total_paye ?? 0) - ($r->total_rembourse ?? 0);
                $r->montant_paye = max($paye, 0);
                $r->montant_restant = max($r->montant_total - $paye, 0);
                return $r;
            });

        return response()->json($reservations);
    }

    /**
     * Création d'une nouvelle réservation.
     */
    public function store(Request $request)
    {
        $this->verifierPermission($request);

        $request->validate([
            'chambre_id'       => 'required|exists:chambres,id',
            'nom_client'       => 'required|string|max:255',
            'telephone_client' => 'required|string|max:50',
            'cni_client'       => 'nullable|string|max:50',
            'nombre_adultes'   => 'nullable|integer|min:1',
            'nombre_enfants'   => 'nullable|integer|min:0',
            'mode_paiement'    => 'nullable|string',
            'acompte'          => 'nullable|numeric|min:0',
            'note'             => 'nullable|string',
            'date_arrivee'     => 'required|date',
            'date_depart'      => 'required|date|after:date_arrivee',
            'montant_total'    => 'required|numeric|min:0',
        ]);

        // Vérification du chevauchement de dates pour la même chambre
        $chambreOccupee = Reservation::where('chambre_id', $request->chambre_id)
            ->where(function ($query) use ($request) {
                $query->where('date_arrivee', '<', $request->date_depart)
                    ->where('date_depart', '>', $request->date_arrivee);
            })
            ->whereIn('statut', ['Confirmée', 'En attente', 'Check-in'])
            ->exists();

        if ($chambreOccupee) {
            return response()->json([
                'message' => 'Désolé, cette chambre est déjà réservée sur cette période.'
            ], 422);
        }

        $reservation = Reservation::create([
            'user_id'          => $request->user()->id,
            'chambre_id'       => $request->chambre_id,
            'nom_client'       => $request->nom_client,
            'telephone_client' => $request->telephone_client,
            'cni_client'       => $request->cni_client,
            'nombre_adultes'   => $request->nombre_adultes ?? 1,
            'nombre_enfants'   => $request->nombre_enfants ?? 0,
            'mode_paiement'    => $request->mode_paiement ?? 'Espèces',
            'acompte'          => $request->acompte ?? 0,
            'note'             => $request->note,
            'date_arrivee'     => $request->date_arrivee,
            'date_depart'      => $request->date_depart,
            'montant_total'    => $request->montant_total,
            'statut_paiement'  => 'En attente',
            'statut'           => 'En attente',
        ]);

        return response()->json([
            'message'     => 'Réservation enregistrée avec succès !',
            'reservation' => $reservation->load(['chambre', 'user']),
        ], 201);
    }

    /**
     * Affichage d'une réservation spécifique.
     */
    public function show($id)
    {
        $reservation = Reservation::with(['chambre', 'user'])->findOrFail($id);
        return response()->json($reservation);
    }

    /**
     * Mise à jour d'une réservation existante.
     */
    public function update(Request $request, $id)
    {
        $this->verifierPermission($request);

        $reservation = Reservation::findOrFail($id);

        $validated = $request->validate([
            'chambre_id'       => 'sometimes|exists:chambres,id',
            'nom_client'       => 'sometimes|string|max:255',
            'telephone_client' => 'sometimes|string|max:50',
            'cni_client'       => 'nullable|string|max:50',
            'nombre_adultes'   => 'nullable|integer|min:1',
            'nombre_enfants'   => 'nullable|integer|min:0',
            'mode_paiement'    => 'nullable|string',
            'acompte'          => 'nullable|numeric|min:0',
            'note'             => 'nullable|string',
            'date_arrivee'     => 'sometimes|date',
            'date_depart'      => 'sometimes|date|after:date_arrivee',
            'montant_total'    => 'sometimes|numeric|min:0',
            'statut'           => 'sometimes|string',
        ]);

        // Vérification de la disponibilité si la chambre ou les dates changent
        if (isset($validated['chambre_id']) || isset($validated['date_arrivee']) || isset($validated['date_depart'])) {
            $chambreId   = $validated['chambre_id'] ?? $reservation->chambre_id;
            $dateArrivee = $validated['date_arrivee'] ?? $reservation->date_arrivee;
            $dateDepart  = $validated['date_depart'] ?? $reservation->date_depart;

            $chambreOccupee = Reservation::where('chambre_id', $chambreId)
                ->where('id', '!=', $reservation->id)
                ->where(function ($query) use ($dateArrivee, $dateDepart) {
                    $query->where('date_arrivee', '<', $dateDepart)
                        ->where('date_depart', '>', $dateArrivee);
                })
                ->whereIn('statut', ['Confirmée', 'En attente', 'Check-in'])
                ->exists();

            if ($chambreOccupee) {
                return response()->json([
                    'message' => 'Cette chambre est déjà réservée sur cette période.'
                ], 422);
            }
        }

        $reservation->update($validated);

        return response()->json([
            'message'     => 'Réservation modifiée avec succès !',
            'reservation' => $reservation->load(['chambre', 'user']),
        ], 200);
    }

    /**
     * Suppression d'une réservation.
     */
    public function destroy(Request $request, $id)
    {
        $this->verifierPermission($request);

        $reservation = Reservation::findOrFail($id);
        $reservation->delete();

        return response()->json([
            'message' => 'Réservation supprimée avec succès !'
        ], 200);
    }

    // Exemple dans ReservationController.php
    public function checkOut(Request $request, $reservationId)
    {
        $reservation = Reservation::findOrFail($reservationId);

        // 1. Mettre à jour la réservation
        $reservation->update([
            'statut' => 'termine', // ou 'out'
            'date_depart_effective' => now(),
        ]);

        // 2. Mettre la chambre en statut 'nettoyage' pour le Housekeeping
        $chambre = Chambre::findOrFail($reservation->chambre_id);
        $chambre->update([
            'statut' => 'nettoyage' // <-- C'est ici qu'on prévient le housekeeping
        ]);

        return response()->json([
            'message' => 'Check-out effectué, chambre mise en nettoyage',
            'chambre' => $chambre
        ]);
    }
}
