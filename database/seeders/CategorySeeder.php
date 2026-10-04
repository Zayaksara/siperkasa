<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /** The 16 "Kelompok Aset" values found in the BMN export, plus 'Bangunan & Sarana' for building parts. */
    public const NAMES = [
        'Furnitur', 'Komputer & Server', 'Alat Laboratorium', 'Alat Kantor',
        'Periferal & Storage', 'Elektronik', 'Alat Medis', 'Audio & Studio',
        'Perangkat Jaringan', 'Kabel & Infrastruktur', 'Keamanan & Sistem IT',
        'Fasilitas & Safety', 'Alat Ukur', 'Lain-lain', 'Mesin & Perkakas',
        'Alat Komunikasi', 'Bangunan & Sarana',
    ];

    public function run(): void
    {
        foreach (self::NAMES as $name) {
            Category::firstOrCreate(['name' => $name]);
        }
    }
}
