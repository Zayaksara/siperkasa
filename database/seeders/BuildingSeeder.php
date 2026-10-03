<?php

namespace Database\Seeders;

use App\Models\Building;
use Illuminate\Database\Seeder;

class BuildingSeeder extends Seeder
{
    /** Placeholder buildings — replace with the real campus list (see spec "Further Notes"). */
    public function run(): void
    {
        foreach (['A' => 'Gedung Contoh A', 'B' => 'Gedung Contoh B', 'C' => 'Gedung Contoh C'] as $code => $name) {
            $building = Building::firstOrCreate(['code' => "G{$code}"], ['name' => $name]);

            foreach ([['101', 'Ruang 101', 1], ['102', 'Ruang 102', 1], ['201', 'Ruang 201', 2], ['202', 'Ruang 202', 2]] as [$roomCode, $roomName, $floor]) {
                $building->rooms()->firstOrCreate(['code' => $roomCode], ['name' => $roomName, 'floor' => $floor]);
            }
        }
    }
}
