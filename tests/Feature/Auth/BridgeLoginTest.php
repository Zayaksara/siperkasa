<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

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

test('new nim login hits bridge once: login, profil, logout', function () {
    fakeBridge();

    loginAs();

    $this->assertAuthenticated();
    Http::assertSentCount(3);
});

test('login page exposes portalLogin false when bridge is off', function () {
    config(['services.bridge.base_url' => '']);

    $this->get(route('login'))->assertInertia(fn ($page) => $page
        ->component('auth/Login')
        ->where('portalLogin', false));
});

test('login page exposes portalLogin true when bridge is on', function () {
    $this->get(route('login'))->assertInertia(fn ($page) => $page
        ->component('auth/Login')
        ->where('portalLogin', true));
});

test('login is throttled per ip across different nims', function () {
    config(['services.bridge.base_url' => '']);

    foreach (range(1, 20) as $i) {
        loginAs('nim-'.$i, 'x')->assertSessionHasErrors('nim_nip');
    }

    loginAs('nim-21', 'x')->assertStatus(429);
});

test('existing role-user with blank email gets free profile email', function () {
    $user = User::factory()->create(['nim_nip' => BRIDGE_NIM, 'password' => null, 'email' => null]);
    fakeBridge();

    loginAs();

    expect($user->fresh()->email)->toBe('budi@kampus.test');
});

test('existing role-user with blank email stays null when profile email is taken', function () {
    User::factory()->create(['email' => 'budi@kampus.test']);
    $user = User::factory()->create(['nim_nip' => BRIDGE_NIM, 'password' => null, 'email' => null]);
    fakeBridge();

    loginAs();

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->email)->toBeNull();
});

function portalProfile(array $override = []): array
{
    return array_merge([
        'nim' => BRIDGE_NIM,
        'nama' => 'Budi Portal',
        'no_hp' => '0812-0000-0000',
        'email' => 'budi@pribadi.test',
        'email_kampus' => 'budi@kampus.test',
        'fakultas_nama' => 'Fakultas Teknik',
        'program_studi_kode' => 'TI01',
        'program_studi_nama' => 'Teknik Informatika',
        'jenjang' => 'S1',
        'tahun_masuk' => 2021,
    ], $override);
}

function fakePortalLogin(array $profile): void
{
    Http::fake([
        'bridge.test/api/v2/portal/login' => Http::response(['data' => ['token' => 'tok', 'role' => 'mahasiswa']]),
        'bridge.test/api/v2/profil' => Http::response(['data' => $profile]),
        'bridge.test/api/v2/logout' => Http::response([]),
    ]);
}

test('new portal user gets all profile fields stored', function () {
    fakePortalLogin(portalProfile());

    loginAs();

    $user = User::where('nim_nip', BRIDGE_NIM)->firstOrFail();
    expect($user->email)->toBe('budi@kampus.test')
        ->and($user->phone)->toBe('081200000000')
        ->and($user->portal_role)->toBe('mahasiswa')
        ->and($user->faculty)->toBe('Fakultas Teknik')
        ->and($user->study_program_code)->toBe('TI01')
        ->and($user->study_program)->toBe('Teknik Informatika')
        ->and($user->degree_level)->toBe('S1')
        ->and($user->entry_year)->toBe(2021)
        ->and($user->portal_synced_at)->not->toBeNull()
        ->and($user->isPortalAccount())->toBeTrue();
});

test('locally created user is not a portal account', function () {
    expect(User::factory()->create()->isPortalAccount())->toBeFalse();
});

test('existing user is re-synced and changed prodi overwrites', function () {
    $user = User::factory()->create([
        'nim_nip' => BRIDGE_NIM, 'password' => null, 'email' => 'lama@kampus.test',
        'faculty' => 'Fakultas Lama', 'study_program' => 'Prodi Lama', 'study_program_code' => 'OLD',
        'degree_level' => 'D3', 'entry_year' => 2019, 'phone' => '081311111111',
    ]);
    fakePortalLogin(portalProfile());

    loginAs();

    $fresh = $user->fresh();
    expect($fresh->faculty)->toBe('Fakultas Teknik')
        ->and($fresh->study_program)->toBe('Teknik Informatika')
        ->and($fresh->study_program_code)->toBe('TI01')
        ->and($fresh->degree_level)->toBe('S1')
        ->and($fresh->entry_year)->toBe(2021)
        ->and($fresh->phone)->toBe('081200000000')
        ->and($fresh->email)->toBe('budi@kampus.test')
        ->and($fresh->portal_synced_at)->not->toBeNull();
});

test('profile outage keeps previous academic fields and phone', function () {
    $user = User::factory()->create([
        'nim_nip' => BRIDGE_NIM, 'password' => null, 'name' => 'Nama Asli', 'email' => 'lama@kampus.test',
        'faculty' => 'Fakultas Lama', 'study_program' => 'Prodi Lama', 'study_program_code' => 'OLD',
        'degree_level' => 'D3', 'entry_year' => 2019, 'phone' => '081311111111',
    ]);
    Http::fake([
        'bridge.test/api/v2/portal/login' => Http::response(['data' => ['token' => 'tok', 'role' => 'mahasiswa']]),
        'bridge.test/api/v2/profil' => Http::response('x', 500),
        'bridge.test/api/v2/logout' => Http::response([]),
    ]);

    loginAs();

    $fresh = $user->fresh();
    expect($fresh->name)->toBe('Nama Asli')
        ->and($fresh->email)->toBe('lama@kampus.test')
        ->and($fresh->faculty)->toBe('Fakultas Lama')
        ->and($fresh->study_program)->toBe('Prodi Lama')
        ->and($fresh->study_program_code)->toBe('OLD')
        ->and($fresh->degree_level)->toBe('D3')
        ->and($fresh->entry_year)->toBe(2019)
        ->and($fresh->phone)->toBe('081311111111')
        ->and($fresh->portal_synced_at)->not->toBeNull();
});

test('portal email overwrites current when free', function () {
    $user = User::factory()->create(['nim_nip' => BRIDGE_NIM, 'password' => null, 'email' => 'lama@kampus.test']);
    fakePortalLogin(portalProfile());

    loginAs();

    expect($user->fresh()->email)->toBe('budi@kampus.test');
});

test('portal email held by another user keeps current email', function () {
    User::factory()->create(['email' => 'budi@kampus.test']);
    $user = User::factory()->create(['nim_nip' => BRIDGE_NIM, 'password' => null, 'email' => 'lama@kampus.test']);
    fakePortalLogin(portalProfile());

    loginAs();

    expect($user->fresh()->email)->toBe('lama@kampus.test');
});

test('forbidden portal fields are never stored', function () {
    foreach ([
        'tempat_lahir', 'tanggal_lahir', 'alamat', 'nama_bapak', 'nama_ibu', 'ip_kumulatif',
        'jenis_kelamin', 'dosen_wali', 'dosen_wali_username', 'foto', 'status', 'semester_lulus', 'tanggal_lulus',
    ] as $column) {
        expect(Schema::hasColumn('users', $column))->toBeFalse();
    }
});

test('overlong portal values do not break login', function () {
    fakePortalLogin(portalProfile([
        'no_hp' => str_repeat('8', 40),
        'program_studi_kode' => str_repeat('A', 30),
    ]));

    $response = loginAs();

    $user = User::where('nim_nip', BRIDGE_NIM)->firstOrFail();
    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(route('dashboard', absolute: false));
    expect($user->phone)->toBeNull()
        ->and($user->study_program_code)->toBeNull()
        ->and($user->faculty)->toBe('Fakultas Teknik');
});

test('profile outage still sets portal role from login data', function () {
    $user = User::factory()->create(['nim_nip' => BRIDGE_NIM, 'password' => null, 'portal_role' => null, 'faculty' => 'Fakultas Lama']);
    Http::fake([
        'bridge.test/api/v2/portal/login' => Http::response(['data' => ['token' => 'tok', 'role' => 'mahasiswa']]),
        'bridge.test/api/v2/profil' => Http::response('x', 500),
        'bridge.test/api/v2/logout' => Http::response([]),
    ]);

    loginAs();

    expect($user->fresh()->portal_role)->toBe('mahasiswa')
        ->and($user->fresh()->faculty)->toBe('Fakultas Lama');
});

test('loaded dosen-style partial profile sets role and nulls academic fields', function () {
    $user = User::factory()->create([
        'nim_nip' => BRIDGE_NIM, 'password' => null, 'faculty' => 'Fakultas Lama',
        'study_program' => 'Prodi Lama', 'entry_year' => 2019, 'phone' => '081311111111',
    ]);
    Http::fake([
        'bridge.test/api/v2/portal/login' => Http::response(['data' => ['token' => 'tok', 'role' => 'dosen']]),
        'bridge.test/api/v2/profil' => Http::response(['data' => ['nama' => 'Dosen Portal']]),
        'bridge.test/api/v2/logout' => Http::response([]),
    ]);

    loginAs();

    $fresh = $user->fresh();
    expect($fresh->portal_role)->toBe('dosen')
        ->and($fresh->name)->toBe('Dosen Portal')
        ->and($fresh->faculty)->toBeNull()
        ->and($fresh->study_program)->toBeNull()
        ->and($fresh->entry_year)->toBeNull()
        ->and($fresh->phone)->toBeNull();
});
