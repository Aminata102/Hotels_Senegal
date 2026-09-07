<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Chambre;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class ChambreController extends Controller
{
    /**
     * Vérification stricte des permissions (Admin, Gérant, Réceptionniste)
     */
    private function verifierPermission(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            abort(401, "Utilisateur non authentifié.");
        }

        $rawRole = $user->role;
        if (is_object($rawRole)) {
            $rawRole = $rawRole->code ?? $rawRole->nom ?? $rawRole->name ?? '';
        } elseif (is_array($rawRole)) {
            $rawRole = $rawRole['code'] ?? $rawRole['nom'] ?? $rawRole['name'] ?? '';
        }

        $role = strtolower(trim((string) $rawRole));

        // 🟢 Rôles autorisés pour la gestion du parc de chambres (LE CAISSIER EST EXCLU)
        $rolesAutorises = [
            'admin',
            'administrateur',
            'gerant',
            'gérant',
            'receptionniste',
            'réceptionniste',
            'receptionist'
        ];

        if (!in_array($role, $rolesAutorises)) {
            abort(403, "Vous n'avez pas la permission de gérer les chambres.");
        }
    }

    /**
     * GET /api/chambres
     */
    public function index()
    {
        $chambres = Chambre::all();
        return response()->json([
            'success' => true,
            'data'    => $chambres
        ]);
    }

    /**
     * POST /api/chambres
     */
    public function store(Request $request)
    {
        // 🔴 Bloque immédiatement si l'utilisateur est Caissier ou non autorisé (HTTP 403)
        $this->verifierPermission($request);

        // Harmonisation de la valeur du prix (compatible prix_nuit et prix_nuitee)
        $prixInput = $request->input('prix') ?? $request->input('prix_nuit') ?? $request->input('prix_nuitee');
        $request->merge(['prix_nuit' => $prixInput]);

        $validator = Validator::make($request->all(), [
            'numero'      => 'required|string|unique:chambres,numero',
            'type'        => 'required|string',
            'prix_nuit'   => 'required|numeric',
            'etage'       => 'nullable|integer',
            'statut'      => 'nullable|string',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Données invalides',
                'errors'  => $validator->errors()
            ], 422);
        }

        // Construction dynamique des données selon le schéma de la table SQL
        $data = [
            'numero'      => $request->numero,
            'type'        => $request->type,
            'etage'       => $request->etage ?? 1,
            'statut'      => $request->statut ?? 'disponible',
            'description' => $request->description,
        ];

        if (Schema::hasColumn('chambres', 'prix_nuitee')) {
            $data['prix_nuitee'] = $prixInput;
        } else {
            $data['prix_nuit'] = $prixInput;
        }

        $chambre = Chambre::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Chambre créée avec succès',
            'data'    => $chambre
        ], 201);
    }

    // Exemple dans ChambreController.php
    public function updateStatut(Request $request, $id)
    {
        $request->validate([
            'statut' => 'required|in:disponible,occupee,nettoyage,propre,maintenance',
        ]);

        $chambre = Chambre::findOrFail($id);
        $chambre->statut = $request->statut;
        $chambre->save();

        return response()->json($chambre);
    }

    public function update(Request $request, $id)
    {
        $chambre = Chambre::find($id);

        if (!$chambre) {
            return response()->json(['message' => 'Chambre non trouvée'], 404);
        }

        $validated = $request->validate([
            'statut' => 'sometimes|string',
            'nom' => 'sometimes|string|max:255',
            'type' => 'sometimes|string',
            'prix' => 'sometimes|numeric',
            'etage' => 'sometimes|integer',
        ]);

        $chambre->update($validated);

        return response()->json([
            'message' => 'Chambre mise à jour avec succès',
            'data' => $chambre,
        ], 200);
    }
}
