<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;

test('re-seeding keeps a changed admin password', function () {
    $this->seed(DatabaseSeeder::class);

    $admin = User::where('nim_nip', 'admin')->firstOrFail();
    $admin->forceFill(['password' => 'rahasia-baru-123'])->save();

    $this->seed(DatabaseSeeder::class);

    expect(Hash::check('rahasia-baru-123', $admin->fresh()->password))->toBeTrue();
});

test('seeding outside local requires SUPER_ADMIN_PASSWORD', function () {
    $original = app()['env'];
    $previous = getenv('SUPER_ADMIN_PASSWORD');
    putenv('SUPER_ADMIN_PASSWORD=');
    unset($_ENV['SUPER_ADMIN_PASSWORD'], $_SERVER['SUPER_ADMIN_PASSWORD']);

    try {
        app()->detectEnvironment(fn () => 'production');

        expect(fn () => (new DatabaseSeeder)->run())
            ->toThrow(RuntimeException::class, 'Set SUPER_ADMIN_PASSWORD before seeding outside local.');
    } finally {
        app()->detectEnvironment(fn () => $original);
        if ($previous !== false) {
            putenv('SUPER_ADMIN_PASSWORD='.$previous);
        }
    }
});
