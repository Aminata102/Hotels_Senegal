<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Chambre extends Model
{
    use HasFactory;

    // C'est cette ligne qui permet à Chambre::create() de fonctionner
    protected $fillable = [
        'numero',
        'type',
        'prix_nuitee',
        'statut',
        'description'
    ];
}
