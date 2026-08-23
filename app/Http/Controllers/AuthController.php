<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        // 1. Vérification de l'existence et du mot de passe
        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Identifiants incorrects'
            ], 401);
        }

        // 2. Vérification si le compte est actif
        if (isset($user->actif) && ! $user->actif) {
            return response()->json([
                'success' => false,
                'message' => 'Votre compte est désactivé. Veuillez contacter l\'administrateur.'
            ], 403);
        }

        // On crée un jeton (Token) pour Flutter
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'token'   => $token,
            'user'    => [
                'id'        => $user->id,
                'nom'       => $user->nom ?? $user->name,
                'prenom'    => $user->prenom ?? '',
                'email'     => $user->email,
                'telephone' => $user->telephone,
                'role'      => $user->role,
                'actif'     => $user->actif,
                'sms'       => $user->sms,
                'hotel'     => $user->hotel,
            ],
        ]);
    }

    public function register(Request $request)
    {
        // Validation des données entrantes depuis Flutter
        $request->validate([
            'nom'       => 'required|string|max:255',
            'prenom'    => 'nullable|string|max:255',
            'email'     => 'required|email|unique:users,email',
            'telephone' => 'required|string|max:20',
            'password'  => 'required|min:6',
            // 👇 VOTRE LIGNE DE CODE ICI 👇
            'role'      => 'required|string|in:administrateur,admin,gerant,gérant,receptionniste,caissier,housekeeping', // 👈 Validation stricte des rôles
            'actif'     => 'required|boolean',
            'sms'       => 'required|boolean',
            'hotel'     => 'nullable|integer',
        ]);

        // Séparation automatique du "Nom complet" s'il est envoyé dans un seul champ 'nom'
        $nomComplet = trim($request->nom);
        $nom = $nomComplet;
        $prenom = $request->prenom ?? '';

        if (empty($prenom) && str_contains($nomComplet, ' ')) {
            $parts = explode(' ', $nomComplet, 2);
            $prenom = $parts[0];
            $nom = $parts[1];
        }

        $user = User::create([
            'nom'       => $nom,
            'prenom'    => $prenom,
            'email'     => $request->email,
            'telephone' => $request->telephone,
            'password'  => Hash::make($request->password),
            'role'      => $request->role,
            'actif'     => $request->actif,
            'sms'       => $request->sms,
            'hotel'     => $request->hotel,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Compte créé avec succès',
            'token'   => $token,
            'user'    => $user,
        ], 201);
    }

    /**
     * Modifier son propre profil
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'nom'       => 'sometimes|required|string|max:255',
            'prenom'    => 'nullable|string|max:255',
            'email'     => 'sometimes|required|email|unique:users,email,' . $user->id,
            'telephone' => 'sometimes|required|string|max:20',
            'password'  => 'nullable|min:6',
        ]);

        if ($request->has('nom')) {
            $user->nom = $request->nom;
        }
        if ($request->has('prenom')) {
            $user->prenom = $request->prenom;
        }
        if ($request->has('email')) {
            $user->email = $request->email;
        }
        if ($request->has('telephone')) {
            $user->telephone = $request->telephone;
        }

        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }

        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Profil mis à jour avec succès',
            'user'    => $user,
        ]);
    }
    private function formatUserResponse(User $user): array
    {
        return [
            'id'        => $user->id,
            'nom'       => $user->nom ?? $user->name,
            'prenom'    => $user->prenom ?? '',
            'email'     => $user->email,
            'telephone' => $user->telephone,
            'role'      => $user->role,
            'actif'     => (bool) $user->actif,
            'sms'       => (bool) $user->sms,
            'hotel'     => $user->hotel,
        ];
    }
}
