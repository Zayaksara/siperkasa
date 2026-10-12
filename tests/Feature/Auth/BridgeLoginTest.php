<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Http;

const BRIDGE_NIM = '2101010001';
const BRIDGE_PASSWORD = 'rahasia-portal';

function fakeBridge(array $profile = ['nama' => 'Budi Portal', 'email' => 'budi@kampus.test']): void
{
    Http::fake([
        'bridge.test/api/v2/portal/login' => Http::response(['data' => ['token' => 'tok']]),
        'bridge.test/api/v2/profil' => Http::response(['data' => $profile]),
        'bridge.test/api/v2/logout' => Http::response([]),
    ]);
}

function loginAs(string $nim = BRIDGE_NIM, string $password = BRIDGE_PASSWORD)
{
    return test()->post(route('login.store'), ['nim_nip' => $nim, 'password' => $password]);
}

beforeEach(function () {
    config(['services.bridge.base_url' => 'https://bridge.test']);
    Http::preventStrayRequests();
});

test('new nim is created from bridge and logged in', function () {
    fakeBridge();

    $response = loginAs();

    $user = User::where('nim_nip', BRIDGE_NIM)->firstOrFail();
    expect($user->name)->toBe('Budi Portal')
        ->and($user->email)->toBe('budi@kampus.test')
        ->and($user->role)->toBe(UserRole::User)
        ->and($user->is_active)->toBeTrue()
        ->and($user->password)->toBeNull();
    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('existing role-user without local password logs in and name updates', function () {
    $user = User::factory()->create(['nim_nip' => BRIDGE_NIM, 'name' => 'Old', 'password' => null]);
    fakeBridge();

    loginAs();

    $this->assertAuthenticatedAs($user);
    expect(User::where('nim_nip', BRIDGE_NIM)->count())->toBe(1)
        ->and($user->fresh()->name)->toBe('Budi Portal');
});

test('existing user keeps real name when bridge profile is unavailable', function () {
    $user = User::factory()->create(['nim_nip' => BRIDGE_NIM, 'name' => 'Nama Asli', 'password' => null]);
    Http::fake([
        'bridge.test/api/v2/portal/login' => Http::response(['data' => ['token' => 'tok']]),
        'bridge.test/api/v2/profil' => Http::response('x', 500),
        'bridge.test/api/v2/logout' => Http::response([]),
    ]);

    loginAs();

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->name)->toBe('Nama Asli');
});

test('existing role-user with local password logs in locally without http', function () {
    $user = User::factory()->create(['nim_nip' => BRIDGE_NIM, 'password' => 'local-pass-123']);

    loginAs(BRIDGE_NIM, 'local-pass-123');

    $this->assertAuthenticatedAs($user);
    Http::assertNothingSent();
});

test('admin and pic never reach bridge', function (string $state) {
    User::factory()->{$state}()->create(['nim_nip' => BRIDGE_NIM]);

    loginAs(BRIDGE_NIM, 'wrong-password');

    $this->assertGuest();
    Http::assertNothingSent();
})->with(['admin', 'pic']);

test('bridge rejection keeps guest and creates no user', function () {
    Http::fake(['bridge.test/api/v2/portal/login' => Http::response(['message' => 'no'], 401)]);

    loginAs();

    $this->assertGuest();
    expect(User::where('nim_nip', BRIDGE_NIM)->exists())->toBeFalse();
});

test('bridge outage shows error on nim_nip', function () {
    Http::fake(['bridge.test/api/v2/portal/login' => Http::response('boom', 500)]);

    loginAs()->assertSessionHasErrors([
        'nim_nip' => 'Login portal kampus sedang tidak dapat dihubungi. Coba lagi beberapa saat.',
    ]);

    $this->assertGuest();
});

test('inactive role-user is rejected after bridge success', function () {
    User::factory()->inactive()->create(['nim_nip' => BRIDGE_NIM, 'password' => null]);
    fakeBridge();

    loginAs()->assertSessionHasErrors(['nim_nip' => 'Akun Anda nonaktif. Hubungi admin.']);

    $this->assertGuest();
});

test('soft-deleted role-user is not restored', function () {
    $user = User::factory()->create(['nim_nip' => BRIDGE_NIM, 'password' => null]);
    $user->delete();
    fakeBridge();

    loginAs()->assertSessionHasErrors(['nim_nip' => 'Akun Anda nonaktif. Hubungi admin.']);

    $this->assertGuest();
    expect(User::withTrashed()->where('nim_nip', BRIDGE_NIM)->count())->toBe(1)
        ->and(User::withTrashed()->find($user->id)->trashed())->toBeTrue();
});

test('profile email used by another user becomes null', function () {
    User::factory()->create(['email' => 'budi@kampus.test']);
    fakeBridge();

    loginAs();

    expect(User::where('nim_nip', BRIDGE_NIM)->firstOrFail()->email)->toBeNull();
});

test('blank base url sends no http and stays guest', function () {
    config(['services.bridge.base_url' => '']);

    loginAs();

    $this->assertGuest();
    Http::assertNothingSent();
});
