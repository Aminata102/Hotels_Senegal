<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    use HasFactory;

    protected $table = 'clients';

    protected $fillable = [
        'prenom',
        'nom',
        'telephone',
        'email',
        'numero_cni',
        'nationalite',
        'ville',
        'statut',          // en_sejour | recent | checkout
        'nombre_sejours',
        'statut_paiement', // paye | du | en_attente
    ];

    protected $casts = [
        'nombre_sejours' => 'integer',
    ];

    // ── Accesseur : nom complet ──────────────
    public function getNomCompletAttribute(): string
    {
        return "{$this->prenom} {$this->nom}";
    }

    // ── Accesseur : initiales ────────────────
    public function getInitialesAttribute(): string
    {
        $p = !empty($this->prenom) ? strtoupper($this->prenom[0]) : '';
        $n = !empty($this->nom)    ? strtoupper($this->nom[0])    : '';
        return "{$p}{$n}";
    }

    // ── Relation : réservations ──────────────
    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }
}
