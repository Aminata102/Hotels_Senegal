<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Api\ChambreController;
use App\Http\Controllers\Api\ReservationController;
use App\Http\Controllers\PaiementController;
use App\Http\Controllers\CaisseController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\RegisterController;

// ══════════════════════════════════════════════
//  ROUTES PUBLIQUES (sans token)
// ══════════════════════════════════════════════

Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/login',      [AuthController::class, 'login']); // ancienne route conservée
Route::post('/auth/register', [AuthController::class, 'register']);

// ══════════════════════════════════════════════════════
//  ROUTES PROTÉGÉES (nécessitent le token Sanctum)
// ══════════════════════════════════════════════════════
Route::middleware('auth:sanctum')->group(function () {

    // ✅ Dashboard (La route qui manquait)
    Route::get('dashboard', [DashboardController::class, 'index']);

    // Auth
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('auth/me',      [AuthController::class, 'me']);
    Route::put('/profile/update', [AuthController::class, 'updateProfile']);

    // Chambres
    Route::get('chambres',       [ChambreController::class, 'index']);
    Route::post('chambres',      [ChambreController::class, 'store']);
    Route::get('/chambres/disponibles', [ChambreController::class, 'getChambresDisponibles']); // ✅ DÉPLACÉE ICI — doit passer AVANT chambres/{id}, sinon "disponibles" est interprété comme un {id}
    Route::get('chambres/{id}',  [ChambreController::class, 'show']);
    Route::put('chambres/{id}',  [ChambreController::class, 'update']);
    Route::delete('chambres/{id}', [ChambreController::class, 'destroy']);

    // Réservations
    Route::get('reservations',       [ReservationController::class, 'index']);
    Route::post('reservations',      [ReservationController::class, 'store']);
    Route::get('reservations/{id}',  [ReservationController::class, 'show']);
    Route::put('reservations/{id}',  [ReservationController::class, 'update']);   // ✅ AJOUTÉE — modification complète (Flutter en avait besoin)
    Route::patch('reservations/{id}', [ReservationController::class, 'update']);  // ✅ AJOUTÉE — alias PATCH, au cas où
    Route::patch('reservations/{id}/statut', [ReservationController::class, 'updateStatut']);
    Route::delete('reservations/{id}', [ReservationController::class, 'destroy']);

    // Paiements
    Route::get('paiements',                [PaiementController::class, 'index']);
    Route::post('paiements',               [PaiementController::class, 'store']);
    Route::get('paiements/stats',          [PaiementController::class, 'stats']);         // ✅ route fixe — reste AVANT paiements/{id}
    Route::post('paiements/remboursement', [PaiementController::class, 'storeRemboursement']); // ✅ AJOUTÉE
    Route::get('paiements/{id}',           [PaiementController::class, 'show']);          // ✅ AJOUTÉE — dynamique, doit rester APRÈS les routes fixes ci-dessus

    // Caisse
    Route::get('caisse/statut',         [CaisseController::class, 'statutActuelle']); // ✅ AJOUTÉE
    Route::post('caisse/ouvrir',        [CaisseController::class, 'ouvrir']);         // ✅ AJOUTÉE
    Route::post('caisse/fermer',        [CaisseController::class, 'fermer']);         // ✅ AJOUTÉE
    Route::get('caisse/historique',     [CaisseController::class, 'historique']);     // ✅ AJOUTÉE

     // Clients
    Route::get   ('/clients',     [ClientController::class, 'index']);
    Route::post  ('/clients',     [ClientController::class, 'store']);
    Route::get   ('/clients/{id}',[ClientController::class, 'show']);
    Route::put   ('/clients/{id}',[ClientController::class, 'update']);
    Route::delete('/clients/{id}',[ClientController::class, 'destroy']);

});
