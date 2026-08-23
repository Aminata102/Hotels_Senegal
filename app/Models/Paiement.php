<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo; // ✅ AJOUTÉ — manquait, causait un crash sur reservation()

class Paiement extends Model
{
    use HasUuids; // Laravel générera l'UUID automatiquement à la création

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'reservation_id',
        'user_id',
        'montant',
        'methode_paiement',
        'type',            // ✅ 'paiement' ou 'remboursement'
        'numero_facture',  // ✅ ex: FAC-20260806-0001
        'note',
    ];

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
