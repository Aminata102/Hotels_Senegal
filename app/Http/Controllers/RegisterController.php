<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class RegisterController extends Controller
{
    public function register(Request $request)
    {
        // Validation
        $validated = $request->validate([
            'nom'        => 'required|string|max:255',
            'email'      => 'required|email|unique:users,email',
            'telephone'  => 'required|string|max:20',
            'password'   => ['required', Password::min(8)],
            'role'       => 'required|string|in:administrateur,receptionniste,caissier,housekeeping',
            'actif'      => 'boolean',
            'hotel_id'   => 'nullable|integer',
        ]);

        // Création de l'utilisateur
        $user = User::create([
            'name'       => $validated['nom'],
            'nom'        => $validated['nom'],
            'prenom'     => '',
            'email'      => $validated['email'],
            'telephone'  => $validated['telephone'],
            'role'       => $validated['role'],
            'actif'      => $validated['actif'] ?? true,
            'hotel_id'   => $validated['hotel_id'] ?? 1,
            'password'   => Hash::make($validated['password']),
        ]);

        // Création du token Sanctum
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Compte créé avec succès.',
            'token'   => $token,
            'user'    => [
                'id'         => $user->id,
                'nom'        => $user->nom,
                'prenom'     => $user->prenom,
                'email'      => $user->email,
                'telephone'  => $user->telephone,
                'role'       => $user->role,
                'actif'      => $user->actif,
                'hotel_id'   => $user->hotel_id,
            ]
        ], 201);
    }
}
