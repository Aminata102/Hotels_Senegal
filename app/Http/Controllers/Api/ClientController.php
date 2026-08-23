<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;

use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ClientController extends Controller
{
    // ✅ Seuls Réceptionniste, Admin et Gérant peuvent créer/modifier/supprimer un client
    // (conforme au diagramme de cas d'usage — le Caissier consulte seulement)
    private function verifierPermission(Request $request)
{
    $user = $request->user();

    if (!$user) {
        return response()->json(['success' => false, 'message' => 'Utilisateur non authentifié.'], 401)->send();
    }

    $rawRole = $user->role;
    if (is_object($rawRole)) {
        $rawRole = $rawRole->code ?? $rawRole->nom ?? $rawRole->name ?? $rawRole->slug ?? '';
    } elseif (is_array($rawRole)) {
        $rawRole = $rawRole['code'] ?? $rawRole['nom'] ?? $rawRole['name'] ?? $rawRole['slug'] ?? '';
    }

    $role = strtolower(trim((string) $rawRole));

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
        response()->json([
            'success' => false,
            'message' => "Vous n'avez pas la permission de gérer les clients."
        ], 403)->send();
        exit;
    }
}

    // ─────────────────────────────────────────
    // GET /api/clients
    // Liste tous les clients avec stats + filtres — ouvert à tous les rôles connectés
    // ─────────────────────────────────────────
    public function index(Request $request)
    {
        $query = Client::query();

        // 🔍 Recherche par nom, téléphone ou CNI
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('nom',        'like', "%$search%")
                  ->orWhere('prenom',   'like', "%$search%")
                  ->orWhere('telephone','like', "%$search%")
                  ->orWhere('numero_cni','like',"%$search%");
            });
        }

        // 🏷️ Filtre par statut
        if ($statut = $request->get('statut')) {
            $query->where('statut', $statut);
        }

        // 🌍 Filtre nationalité
        if ($nationalite = $request->get('nationalite')) {
            $query->where('nationalite', $nationalite);
        }

        $clients = $query->latest()->get();

        // 📊 Stats calculées
        $stats = [
            'total'     => Client::count(),
            'en_sejour' => Client::where('statut', 'en_sejour')->count(),
            'fideles'   => Client::where('nombre_sejours', '>=', 3)->count(),
        ];

        return response()->json([
            'success' => true,
            'data'    => $clients,
            'stats'   => $stats,
        ]);
    }

    // ─────────────────────────────────────────
    // POST /api/clients
    // Créer un nouveau client
    // ─────────────────────────────────────────
    public function store(Request $request)
    {
        $this->verifierPermission($request); // ✅ AJOUTÉ — bloque le caissier (403)

        $validator = Validator::make($request->all(), [
            'prenom'      => 'required|string|max:100',
            'nom'         => 'required|string|max:100',
            'telephone'   => 'required|string|max:20',
            'email'       => 'nullable|email|max:150',
            'numero_cni'  => 'nullable|string|max:50',
            'nationalite' => 'nullable|string|max:100',
            'ville'       => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Données invalides',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $client = Client::create([
            'prenom'         => $request->prenom,
            'nom'            => $request->nom,
            'telephone'      => $request->telephone,
            'email'          => $request->email,
            'numero_cni'     => $request->numero_cni,
            'nationalite'    => $request->nationalite ?? 'Sénégalaise',
            'ville'          => $request->ville,
            'statut'         => 'recent',
            'nombre_sejours' => 0,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Client créé avec succès',
            'data'    => $client,
        ], 201);
    }

    // ─────────────────────────────────────────
    // GET /api/clients/{id}
    // Détails d'un client — ouvert à tous les rôles connectés
    // ─────────────────────────────────────────
    public function show($id)
    {
        $client = Client::find($id);

        if (!$client) {
            return response()->json([
                'success' => false,
                'message' => 'Client introuvable',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $client,
        ]);
    }

    // ─────────────────────────────────────────
    // PUT /api/clients/{id}
    // Modifier un client
    // ─────────────────────────────────────────
    public function update(Request $request, $id)
    {
        $this->verifierPermission($request); // ✅ AJOUTÉ — bloque le caissier (403)

        $client = Client::find($id);

        if (!$client) {
            return response()->json([
                'success' => false,
                'message' => 'Client introuvable',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'prenom'      => 'sometimes|string|max:100',
            'nom'         => 'sometimes|string|max:100',
            'telephone'   => 'sometimes|string|max:20',
            'email'       => 'nullable|email|max:150',
            'numero_cni'  => 'nullable|string|max:50',
            'nationalite' => 'nullable|string|max:100',
            'ville'       => 'nullable|string|max:100',
            'statut'      => 'sometimes|in:en_sejour,recent,checkout',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Données invalides',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $client->update($request->only([
            'prenom', 'nom', 'telephone', 'email',
            'numero_cni', 'nationalite', 'ville', 'statut',
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Client mis à jour',
            'data'    => $client,
        ]);
    }

    // ─────────────────────────────────────────
    // DELETE /api/clients/{id}
    // Supprimer un client
    // ─────────────────────────────────────────
    public function destroy(Request $request, $id)
    {
        $this->verifierPermission($request); // ✅ AJOUTÉ — bloque le caissier (403)

        $client = Client::find($id);

        if (!$client) {
            return response()->json([
                'success' => false,
                'message' => 'Client introuvable',
            ], 404);
        }

        $client->delete();

        return response()->json([
            'success' => true,
            'message' => 'Client supprimé',
        ]);
    }
}
