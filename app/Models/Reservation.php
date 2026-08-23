<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids; // Importation nécessaire
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reservation extends Model
{
    // 1. Toujours mettre les traits et propriétés en haut
    use HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'user_id', 'chambre_id', 'nom_client', 'telephone_client',
        'cni_client', 'nombre_adultes', 'nombre_enfants', 'mode_paiement', 'note', // ✅ AJOUTÉS — manquaient, étaient ignorés silencieusement
        'date_arrivee', 'date_depart', 'montant_total', 'statut', 'statut_paiement',
    ];

    // 2. Les fonctions (relations) viennent après
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function chambre(): BelongsTo
    {
        return $this->belongsTo(Chambre::class);
    }

    // ✅ CORRIGÉ — remplace l'ancienne relation paiement() (HasOne, singulier).
    // Une réservation peut avoir PLUSIEURS paiements (paiements partiels + remboursements),
    // donc HasMany est la bonne relation. C'est celle qu'utilise withSum() dans
    // ReservationController::index() pour calculer montant_paye / montant_restant.
    public function paiements(): HasMany
    {
        return $this->hasMany(Paiement::class);
    }
}
