<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Chambre;
use Illuminate\Http\Request;

class HousekeepingController extends Controller
{
    /**
     * Vérifie les permissions d'accès au module Housekeeping / Nettoyage.
     * L'Administrateur possède un accès global via $user->isAdmin().
     */
    private function verifierPermission(Request $request)
    {
        $user = $request->user();

        if (!$user || (!$user->isAdmin() && !$user->canAccessHousekeeping())) {
            abort(403, "Vous n'avez pas la permission d'accéder au module Housekeeping.");
        }
    }

    /**
     * Liste des chambres et de leur état de nettoyage.
     */
    public function index(Request $request)
    {
        $this->verifierPermission($request);

        $user = $request->user();

        $query = Chambre::with('agent:id,nom,prenom');

        // Si l'utilisateur n'est pas Admin, il voit principalement ses tâches
        // ou les chambres à nettoyer
        if (!$user->isAdmin()) {
            $query->where(function ($q) use ($user) {
                $q->where('agent_id', $user->id)
                  ->orWhereIn('statut_nettoyage', ['sale', 'en_cours']);
            });
        }

        // Filtre optionnel par statut si envoyé par Flutter (?statut=sale)
        if ($request->has('statut')) {
            $query->where('statut_nettoyage', $request->query('statut'));
        }

        $chambres = $query->orderBy('numero', 'asc')->get();

        return response()->json($chambres);
    }

    /**
     * Assigner une chambre à un agent de ménage ou signaler une intervention.
     */
    public function store(Request $request)
    {
        $this->verifierPermission($request);

        $validated = $request->validate([
            'chambre_id'       => 'required|exists:chambres,id',
            'agent_id'         => 'nullable|exists:users,id',
            'statut_nettoyage' => 'required|in:sale,en_cours,propre,inspection',
            'note_nettoyage'   => 'nullable|string',
        ]);

        $chambre = Chambre::findOrFail($validated['chambre_id']);

        $chambre->update([
            'agent_id'         => $validated['agent_id'] ?? $request->user()->id,
            'statut_nettoyage' => $validated['statut_nettoyage'],
            'note_nettoyage'   => $validated['note_nettoyage'] ?? $chambre->note_nettoyage,
        ]);

        return response()->json([
            'message' => 'Tâche de nettoyage enregistrée avec succès !',
            'chambre' => $chambre->load('agent:id,nom,prenom'),
        ], 200);
    }

    /**
     * Détails du statut de nettoyage d'une chambre spécifique.
     */
    public function show(Request $request, string $id)
    {
        $this->verifierPermission($request);

        $chambre = Chambre::with('agent:id,nom,prenom')->findOrFail($id);

        return response()->json($chambre);
    }

    /**
     * Mise à jour du statut de nettoyage (ex: passage de 'sale' à 'en_cours' puis 'propre').
     */
    public function update(Request $request, string $id)
    {
        $this->verifierPermission($request);

        $chambre = Chambre::findOrFail($id);

        $validated = $request->validate([
            'statut_nettoyage' => 'sometimes|in:sale,en_cours,propre,inspection',
            'agent_id'         => 'nullable|exists:users,id',
            'note_nettoyage'   => 'nullable|string',
        ]);

        // Un agent standard ne peut modifier que les chambres qui lui sont assignées ou libres
        if (!$request->user()->isAdmin() && $chambre->agent_id && $chambre->agent_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Cette chambre est assignée à un autre agent de ménage.'
            ], 403);
        }

        $chambre->update($validated);

        return response()->json([
            'message' => 'Statut de nettoyage mis à jour !',
            'chambre' => $chambre->load('agent:id,nom,prenom'),
        ]);
    }

    /**
     * Réinitialiser le statut d'une chambre (remise à zéro de l'agent assigné).
     */
    public function destroy(Request $request, string $id)
    {
        $this->verifierPermission($request);

        $chambre = Chambre::findOrFail($id);

        // Seul l'Admin ou l'agent assigné peut réinitialiser l'assignation
        if (!$request->user()->isAdmin() && $chambre->agent_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Seul un Administrateur peut désassigner cette chambre.'
            ], 403);
        }

        $chambre->update([
            'agent_id'         => null,
            'statut_nettoyage' => 'propre',
            'note_nettoyage'   => null,
        ]);

        return response()->json([
            'message' => 'Assignation réinitialisée avec succès !',
            'chambre' => $chambre,
        ]);
    }
}
