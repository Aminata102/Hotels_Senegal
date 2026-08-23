<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionCaisse extends Model
{
    protected $table = 'sessions_caisse';

    protected $fillable = [
        'user_id',
        'heure_ouverture',
        'heure_fermeture',
        'fond_ouverture',
        'montant_theorique',
        'montant_reel',
        'ecart',
        'statut',
        'note',
    ];

    protected $casts = [
        'heure_ouverture' => 'datetime',
        'heure_fermeture' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
