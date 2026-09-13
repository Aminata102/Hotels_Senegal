<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Chambre;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReservationController extends Controller
{
    /**
     * Rôles autorisés à créer / modifier / annuler une réservation.
     * Le caissier est volontairement exclu ici : il peut consulter et
     * enregistrer un paiement (voir enregistrerPaiement()), mais ne gère
     * pas les réservations elles-mêmes — cohérent avec _peutGererReservations
     * côté Flutter.
     */
    private function verifierGestionReservation(Request $request)
    {
        $user = $request->user();

        if (!$user || !($user->isAdmin() || $user->isGerant() || $user->isReceptionniste())) {
            abort(403, "Vous n'avez pas la permission de gérer les réservations.");
        }
    }

    /**
     * Rôles autorisés à enregistrer un paiement sur une réservation.
     */
    private function verifierEncaissement(Request $request)
    {
        $user = $request->user();

        if (!$user || !($user->isAdmin() || $user->isGerant() || $user->isReceptionniste() || $user->isCaissier())) {
            abort(403, "Vous n'avez pas la permission d'encaisser un paiement.");
        }
    }

    /**
     * Recalcule le statut de paiement et le montant restant à partir de
     * l'acompte versé et du montant total. Centralisé ici pour ne jamais
     * désynchroniser ces trois valeurs entre elles.
     */
    private function calculerEtatPaiement(float $montantTotal, float $acompte): array
    {
        $acompte = max(0, min($acompte, $montantTotal));
        $montantRestant = round($montantTotal - $acompte, 2);

        if ($montantRestant <= 0 && $montantTotal > 0) {
            $statutPaiement = 'Payé';
        } elseif ($acompte > 0) {
            $statutPaiement = 'Partiel';
        } else {
            $statutPaiement = 'Non payé';
        }

        return [
            'acompte'          => $acompte,
            'montant_restant'  => $montantRestant,
            'statut_paiement'  => $statutPaiement,
        ];
    }

    /**
     * Liste des réservations. Le filtrage par onglet (Confirmées / En attente
     * / Check-in / Annulées) est fait côté Flutter sur les données brutes,
     * donc on renvoie systématiquement l'ensemble et on laisse le frontend
     * filtrer sur statut_paiement / date_depart / statut.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            abort(401, "Non authentifié.");
        }

        $query = Reservation::with('chambre');

        if ($request->has('hotel_id')) {
            $query->where('hotel_id', $request->query('hotel_id'));
        } elseif ($user->hotel_id) {
            $query->where('hotel_id', $user->hotel_id);
        }

        // Filtre optionnel côté serveur si jamais le frontend en a besoin
        if ($request->has('statut')) {
            $query->where('statut', $request->query('statut'));
        }
        if ($request->has('statut_paiement')) {
            $query->where('statut_paiement', $request->query('statut_paiement'));
        }

        $reservations = $query->orderBy('date_arrivee', 'desc')->get();

        return response()->json(['data' => $reservations]);
    }

    /**
     * Détails d'une réservation.
     */
    public function show(Request $request, string $id)
    {
        if (!$request->user()) {
            abort(401, "Non authentifié.");
        }

        $reservation = Reservation::with('chambre')->findOrFail($id);

        return response()->json(['data' => $reservation]);
    }

    /**
     * Création d'une réservation.
     */
    public function store(Request $request)
    {
        $this->verifierGestionReservation($request);

        $validated = $request->validate([
            'chambre_id'        => 'required|exists:chambres,id',
            'nom_client'         => 'required|string|max:255',
            'telephone_client'   => 'nullable|string|max:30',
            'cni_client'         => 'nullable|string|max:50',
            'nombre_adultes'     => 'required|integer|min:1',
            'nombre_enfants'     => 'required|integer|min:0',
            'mode_paiement'      => 'required|string|in:Espèces,Orange Money,Wave,Carte bancaire',
            'acompte'            => 'nullable|numeric|min:0',
            'note'               => 'nullable|string',
            'date_arrivee'       => 'required|date',
            'date_depart'        => 'required|date|after:date_arrivee',
            'montant_total'      => 'required|numeric|min:0',
        ]);

        $chambre = Chambre::findOrFail($validated['chambre_id']);

        $statutChambre = strtolower($chambre->statut ?? '');
        if (!in_array($statutChambre, ['disponible', 'libre'])) {
            return response()->json([
                'message' => "Cette chambre n'est pas disponible pour une nouvelle réservation."
            ], 422);
        }

        $etatPaiement = $this->calculerEtatPaiement(
            (float) $validated['montant_total'],
            (float) ($validated['acompte'] ?? 0)
        );

        $reservation = DB::transaction(function () use ($validated, $etatPaiement, $chambre, $request) {
            $reservation = Reservation::create([
                'chambre_id'        => $validated['chambre_id'],
                'hotel_id'          => $request->user()->hotel_id,
                'nom_client'        => $validated['nom_client'],
                'telephone_client'  => $validated['telephone_client'] ?? null,
                'cni_client'        => $validated['cni_client'] ?? null,
                'nombre_adultes'    => $validated['nombre_adultes'],
                'nombre_enfants'    => $validated['nombre_enfants'],
                'mode_paiement'     => $validated['mode_paiement'],
                'note'              => $validated['note'] ?? null,
                'date_arrivee'      => $validated['date_arrivee'],
                'date_depart'       => $validated['date_depart'],
                'montant_total'     => $validated['montant_total'],
                'acompte'           => $etatPaiement['acompte'],
                'montant_restant'   => $etatPaiement['montant_restant'],
                'statut_paiement'   => $etatPaiement['statut_paiement'],
                'statut'            => 'En attente',
                'agent_id'          => $request->user()->id,
            ]);

            // La chambre devient indisponible pour d'autres réservations
            // tant que celle-ci est active.
            $chambre->update(['statut' => 'occupee']);

            return $reservation;
        });

        return response()->json([
            'message'     => 'Réservation enregistrée avec succès !',
            'reservation' => $reservation->load('chambre'),
        ], 201);
    }

    /**
     * Mise à jour complète d'une réservation existante.
     */
    public function update(Request $request, string $id)
    {
        $this->verifierGestionReservation($request);

        $reservation = Reservation::findOrFail($id);

        $validated = $request->validate([
            'chambre_id'        => 'sometimes|exists:chambres,id',
            'nom_client'         => 'sometimes|string|max:255',
            'telephone_client'   => 'nullable|string|max:30',
            'cni_client'         => 'nullable|string|max:50',
            'nombre_adultes'     => 'sometimes|integer|min:1',
            'nombre_enfants'     => 'sometimes|integer|min:0',
            'mode_paiement'      => 'sometimes|string|in:Espèces,Orange Money,Wave,Carte bancaire',
            'acompte'            => 'nullable|numeric|min:0',
            'note'               => 'nullable|string',
            'date_arrivee'       => 'sometimes|date',
            'date_depart'        => 'sometimes|date|after:date_arrivee',
            'montant_total'      => 'sometimes|numeric|min:0',
        ]);

        // Changement de chambre : on libère l'ancienne, on occupe la nouvelle
        if (isset($validated['chambre_id']) && $validated['chambre_id'] != $reservation->chambre_id) {
            $nouvelleChambre = Chambre::findOrFail($validated['chambre_id']);
            $statutNouvelleChambre = strtolower($nouvelleChambre->statut ?? '');

            if (!in_array($statutNouvelleChambre, ['disponible', 'libre'])) {
                return response()->json([
                    'message' => "La chambre sélectionnée n'est pas disponible."
                ], 422);
            }

            DB::transaction(function () use ($reservation, $nouvelleChambre) {
                $ancienneChambre = Chambre::find($reservation->chambre_id);
                $ancienneChambre?->update(['statut' => 'disponible']);
                $nouvelleChambre->update(['statut' => 'occupee']);
            });
        }

        $montantTotal = $validated['montant_total'] ?? $reservation->montant_total;
        $acompte = $validated['acompte'] ?? $reservation->acompte;
        $etatPaiement = $this->calculerEtatPaiement((float) $montantTotal, (float) $acompte);

        $reservation->update(array_merge($validated, $etatPaiement, [
            'montant_total' => $montantTotal,
        ]));

        return response()->json([
            'message'     => 'Réservation modifiée avec succès !',
            'reservation' => $reservation->load('chambre'),
        ]);
    }

    /**
     * Enregistrement d'un paiement (acompte ou solde) sur une réservation
     * existante, sans passer par une modification complète. Accessible au
     * Caissier, contrairement à update().
     */
    public function enregistrerPaiement(Request $request, string $id)
    {
        $this->verifierEncaissement($request);

        $validated = $request->validate([
            'montant' => 'required|numeric|min:0.01',
        ]);

        $reservation = Reservation::findOrFail($id);

        $nouvelAcompte = (float) $reservation->acompte + (float) $validated['montant'];
        $etatPaiement = $this->calculerEtatPaiement((float) $reservation->montant_total, $nouvelAcompte);

        $reservation->update($etatPaiement);

        return response()->json([
            'message'     => 'Paiement enregistré avec succès !',
            'reservation' => $reservation->load('chambre'),
        ]);
    }

    /**
     * Marque le séjour comme terminé (le client a quitté l'hôtel).
     * La chambre redevient disponible à la réservation, mais est signalée
     * comme "sale" pour apparaître dans le module Housekeeping.
     */
    public function terminerSejour(Request $request, string $id)
    {
        $this->verifierGestionReservation($request);

        $reservation = Reservation::findOrFail($id);

        DB::transaction(function () use ($reservation) {
            $reservation->update(['statut' => 'Terminée']);

            $chambre = Chambre::find($reservation->chambre_id);
            $chambre?->update([
                'statut'           => 'disponible',
                'statut_nettoyage' => 'sale',
            ]);
        });

        return response()->json([
            'message'     => 'Séjour terminé, chambre libérée et signalée au ménage.',
            'reservation' => $reservation->load('chambre'),
        ]);
    }

    /**
     * Annulation d'une réservation. La chambre redevient immédiatement
     * disponible (pas besoin de ménage puisqu'elle n'a pas été occupée).
     */
    public function destroy(Request $request, string $id)
    {
        $this->verifierGestionReservation($request);

        $reservation = Reservation::findOrFail($id);

        DB::transaction(function () use ($reservation) {
            $chambre = Chambre::find($reservation->chambre_id);

            // On ne libère la chambre que si elle n'a pas déjà été libérée
            // (ex: réservation déjà terminée puis supprimée après coup).
            if ($chambre && $reservation->statut !== 'Terminée') {
                $chambre->update(['statut' => 'disponible']);
            }

            $reservation->update(['statut' => 'Annulée']);
            $reservation->delete();
        });

        return response()->json([
            'message' => 'Réservation supprimée avec succès !',
        ]);
    }
}
