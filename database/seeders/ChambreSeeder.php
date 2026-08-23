<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Chambre;

class ChambreSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $chambres = [
            ['numero' => '101', 'type' => 'Simple', 'prix_nuitee' => 25000, 'statut' => 'Libre'],
            ['numero' => '202', 'type' => 'Double', 'prix_nuitee' => 45000, 'statut' => 'Libre'],
            ['numero' => '303', 'type' => 'Suite', 'prix_nuitee' => 85000, 'statut' => 'Occupée'],
        ];

        foreach ($chambres as $chambre) {
            Chambre::create($chambre);
        }
    }
}
