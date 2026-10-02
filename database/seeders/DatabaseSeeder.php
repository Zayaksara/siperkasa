<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Super Admin default
        User::updateOrCreate(
            ['email' => 'admin@siperkasa.local'],
            [
                'nim_nip' => env('SUPER_ADMIN_NIP', 'admin'),
                'name' => 'Super Admin',
                'password' => bcrypt(env('SUPER_ADMIN_PASSWORD', 'password')),
                'role' => 'admin',
            ]
        );
    }
}
