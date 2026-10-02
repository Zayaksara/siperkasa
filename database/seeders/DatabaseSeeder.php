<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['nim_nip' => env('SUPER_ADMIN_NIP', 'admin')],
            [
                'name' => 'Super Admin',
                'email' => 'admin@siperkasa.local',
                'password' => env('SUPER_ADMIN_PASSWORD', 'password'),
                'role' => UserRole::Admin,
                'is_active' => true,
            ],
        );

        if (! app()->environment('local')) {
            return;
        }

        $demo = [
            ['pic001', 'PIC Elektronik', UserRole::Pic],
            ['pic002', 'PIC Furnitur', UserRole::Pic],
            ['2010001', 'Mahasiswa Contoh', UserRole::User],
            ['198501012010011001', 'Dosen Contoh', UserRole::User],
        ];

        foreach ($demo as [$nimNip, $name, $role]) {
            User::updateOrCreate(
                ['nim_nip' => $nimNip],
                ['name' => $name, 'password' => 'password', 'role' => $role, 'is_active' => true],
            );
        }
    }
}
