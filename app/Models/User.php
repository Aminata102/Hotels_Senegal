<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'nom',
        'prenom',
        'email',
        'telephone',
        'role',
        'actif',
        'sms',
        'hotel_id',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'actif'             => 'boolean',
            'sms'               => 'boolean',
            'hotel_id'          => 'integer',
        ];
    }

    // ==========================================
    // MÉTHODES HELPERS DE RÔLES & PERMISSIONS
    // ==========================================

    /**
     * Vérifie si l'utilisateur est Administrateur
     */
    public function isAdmin(): bool
    {
        if (!$this->role) {
            return false;
        }

        return in_array(strtolower(trim($this->role)), ['administrateur', 'admin']);
    }

    /**
     * Vérifie si l'utilisateur est Réceptionniste (ou Admin)
     */
    public function canAccessReception(): bool
    {
        return $this->isAdmin() || strtolower(trim($this->role ?? '')) === 'receptionniste';
    }

    /**
     * Vérifie si l'utilisateur est Caissier (ou Admin)
     */
    public function canAccessCaisse(): bool
    {
        return $this->isAdmin() || strtolower(trim($this->role ?? '')) === 'caissier';
    }

    /**
     * Vérifie si l'utilisateur est Agent de ménage (ou Admin)
     */
    public function canAccessHousekeeping(): bool
    {
        return $this->isAdmin() || strtolower(trim($this->role ?? '')) === 'housekeeping';
    }

    // ==========================================
    // RELATIONS ELOQUENT
    // ==========================================

    /**
     * Relation avec l'hôtel rattaché
     */
    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }
}
