<?php

use App\Support\BridgeClient;
use App\Support\BridgeUnavailableException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.bridge.base_url' => 'https://bridge.test']);
    Http::preventStrayRequests();
});

test('bridge is disabled when base url is blank', function () {
    config(['services.bridge.base_url' => '']);

    expect((new BridgeClient)->enabled())->toBeFalse();
});

test('bridge is enabled when base url is set', function () {
    expect((new BridgeClient)->enabled())->toBeTrue();
});

test('successful login returns mapped profile and logs out', function () {
    Http::fake([
        'bridge.test/api/v2/portal/login' => Http::response(['data' => ['token' => 'tok123']]),
        'bridge.test/api/v2/profil' => Http::response(['data' => ['nama' => 'Budi Santoso', 'email' => 'budi@kampus.test']]),
        'bridge.test/api/v2/logout' => Http::response(['ok' => true]),
    ]);

    $result = (new BridgeClient)->authenticate('2110001', 'secret');

    expect($result)->toBe([
        'username' => '2110001',
        'name' => 'Budi Santoso',
        'email' => 'budi@kampus.test',
        'portal_role' => null,
        'phone' => null,
        'faculty' => null,
        'study_program_code' => null,
        'study_program' => null,
        'degree_level' => null,
        'entry_year' => null,
        'profile_loaded' => true,
    ]);

    Http::assertSent(fn (Request $r) => $r->url() === 'https://bridge.test/api/v2/portal/login'
        && $r['username'] === '2110001'
        && $r['password'] === 'secret');
    Http::assertSent(fn (Request $r) => $r->url() === 'https://bridge.test/api/v2/logout'
        && $r->hasHeader('Authorization', 'Bearer tok123'));
});

test('token at top level also works', function () {
    Http::fake([
        'bridge.test/api/v2/portal/login' => Http::response(['token' => 'toptok']),
        'bridge.test/api/v2/profil' => Http::response(['nama' => 'Siti']),
        'bridge.test/api/v2/logout' => Http::response([]),
    ]);

    expect((new BridgeClient)->authenticate('u1', 'p'))->toMatchArray([
        'username' => 'u1',
        'name' => 'Siti',
        'email' => null,
        'profile_loaded' => true,
    ]);

    Http::assertSent(fn (Request $r) => $r->url() === 'https://bridge.test/api/v2/profil'
        && $r->hasHeader('Authorization', 'Bearer toptok'));
});

test('rejected credentials return null', function (int $status) {
    Http::fake(['bridge.test/api/v2/portal/login' => Http::response(['message' => 'no'], $status)]);

    expect((new BridgeClient)->authenticate('u', 'bad'))->toBeNull();
})->with([401, 422]);

test('login without token returns null', function () {
    Http::fake(['bridge.test/api/v2/portal/login' => Http::response(['data' => []], 200)]);

    expect((new BridgeClient)->authenticate('u', 'p'))->toBeNull();
});

test('non-string token returns null', function () {
    Http::fake(['bridge.test/api/v2/portal/login' => Http::response(['data' => ['token' => ['x']]], 200)]);

    expect((new BridgeClient)->authenticate('u', 'p'))->toBeNull();
});

test('server error throws unavailable', function () {
    Http::fake(['bridge.test/api/v2/portal/login' => Http::response('boom', 500)]);

    (new BridgeClient)->authenticate('u', 'p');
})->throws(BridgeUnavailableException::class);

test('connection failure throws unavailable', function () {
    Http::fake(['bridge.test/*' => Http::failedConnection()]);

    (new BridgeClient)->authenticate('u', 'p');
})->throws(BridgeUnavailableException::class);

test('profile failure falls back to username and null email', function () {
    Http::fake([
        'bridge.test/api/v2/portal/login' => Http::response(['data' => ['token' => 't']]),
        'bridge.test/api/v2/profil' => Http::response('x', 500),
        'bridge.test/api/v2/logout' => Http::response([]),
    ]);

    expect((new BridgeClient)->authenticate('2110002', 'p'))->toBe([
        'username' => '2110002',
        'name' => '2110002',
        'email' => null,
        'portal_role' => null,
        'phone' => null,
        'faculty' => null,
        'study_program_code' => null,
        'study_program' => null,
        'degree_level' => null,
        'entry_year' => null,
        'profile_loaded' => false,
    ]);
});

test('invalid profile email becomes null', function () {
    Http::fake([
        'bridge.test/api/v2/portal/login' => Http::response(['data' => ['token' => 't']]),
        'bridge.test/api/v2/profil' => Http::response(['data' => ['nama' => 'Ani', 'email' => 'not-an-email']]),
        'bridge.test/api/v2/logout' => Http::response([]),
    ]);

    expect((new BridgeClient)->authenticate('u', 'p')['email'])->toBeNull();
});

function fakePortal(array $profile, array $login = []): void
{
    Http::fake([
        'bridge.test/api/v2/portal/login' => Http::response(['data' => array_merge(['token' => 't'], $login)]),
        'bridge.test/api/v2/profil' => Http::response(['data' => $profile]),
        'bridge.test/api/v2/logout' => Http::response([]),
    ]);
}

test('full mahasiswa profile maps all whitelisted fields', function () {
    fakePortal([
        'nim' => '2101010001',
        'nama' => 'Budi Portal',
        'no_hp' => '0812-0000-0000',
        'email' => 'budi@pribadi.test',
        'email_kampus' => 'budi@kampus.test',
        'fakultas_nama' => ' Fakultas Teknik ',
        'program_studi_kode' => 'TI01',
        'program_studi_nama' => 'Teknik Informatika',
        'jenjang' => 'S1',
        'tahun_masuk' => 2021,
    ], ['username' => '2101010001', 'nama' => 'Login Nama', 'role' => ' mahasiswa ']);

    expect((new BridgeClient)->authenticate('2101010001', 'p'))->toBe([
        'username' => '2101010001',
        'name' => 'Budi Portal',
        'email' => 'budi@kampus.test',
        'portal_role' => 'mahasiswa',
        'phone' => '081200000000',
        'faculty' => 'Fakultas Teknik',
        'study_program_code' => 'TI01',
        'study_program' => 'Teknik Informatika',
        'degree_level' => 'S1',
        'entry_year' => 2021,
        'profile_loaded' => true,
    ]);
});

test('name falls back to login data nama when profile is unavailable', function () {
    Http::fake([
        'bridge.test/api/v2/portal/login' => Http::response(['data' => ['token' => 't', 'nama' => 'Login Nama', 'role' => 'dosen']]),
        'bridge.test/api/v2/profil' => Http::response('x', 500),
        'bridge.test/api/v2/logout' => Http::response([]),
    ]);

    $result = (new BridgeClient)->authenticate('u', 'p');

    expect($result['name'])->toBe('Login Nama')
        ->and($result['portal_role'])->toBe('dosen')
        ->and($result['profile_loaded'])->toBeFalse();
});

test('campus email is preferred and invalid one is skipped', function (array $profile, ?string $expected) {
    fakePortal($profile);

    expect((new BridgeClient)->authenticate('u', 'p')['email'])->toBe($expected);
})->with([
    'both' => [['email' => 'a@pribadi.test', 'email_kampus' => 'a@kampus.test'], 'a@kampus.test'],
    'invalid campus' => [['email' => 'a@pribadi.test', 'email_kampus' => 'nope'], 'a@pribadi.test'],
    'only campus' => [['email_kampus' => 'a@kampus.test'], 'a@kampus.test'],
]);

test('phone is normalised', function (mixed $raw, ?string $expected) {
    fakePortal(['nama' => 'X', 'no_hp' => $raw]);

    expect((new BridgeClient)->authenticate('u', 'p')['phone'])->toBe($expected);
})->with([
    'dashes' => ['0812-0000-0000', '081200000000'],
    'spaces dots' => ['0812 0000.0000', '081200000000'],
    'plus' => ['+62 812-0000-0000', '+6281200000000'],
    'too short' => ['12345', null],
    'blank' => ['', null],
    'null' => [null, null],
    'array' => [['x'], null],
]);

test('entry year accepts numeric string and rejects out of range', function (mixed $raw, ?int $expected) {
    fakePortal(['nama' => 'X', 'tahun_masuk' => $raw]);

    expect((new BridgeClient)->authenticate('u', 'p')['entry_year'])->toBe($expected);
})->with([
    'int' => [2021, 2021],
    'string' => ['2019', 2019],
    'too old' => [1900, null],
    'too new' => [2500, null],
    'garbage' => ['abc', null],
    'null' => [null, null],
]);

test('blank academic strings become null', function () {
    fakePortal(['nama' => 'X', 'fakultas_nama' => '  ', 'program_studi_kode' => '', 'program_studi_nama' => null, 'jenjang' => '   ']);

    expect((new BridgeClient)->authenticate('u', 'p'))->toMatchArray([
        'faculty' => null,
        'study_program_code' => null,
        'study_program' => null,
        'degree_level' => null,
    ]);
});

test('forbidden profile fields never appear in the result', function () {
    $forbidden = [
        'tempat_lahir' => 'Kota Dummy',
        'tanggal_lahir' => '2000-01-01',
        'alamat' => 'Jl. Dummy 1',
        'nama_bapak' => 'Bapak Dummy',
        'nama_ibu' => 'Ibu Dummy',
        'ip_kumulatif' => '3.50',
        'jenis_kelamin' => 'L',
        'dosen_wali' => 'Dosen Dummy',
        'dosen_wali_username' => 'dosen01',
        'foto' => 'https://bridge.test/foto.jpg',
        'status' => 'aktif',
        'semester_lulus' => '20241',
        'tanggal_lulus' => '2025-01-01',
    ];
    fakePortal(['nama' => 'Budi', ...$forbidden]);

    $result = (new BridgeClient)->authenticate('u', 'p');

    expect(array_keys($result))->toBe([
        'username', 'name', 'email', 'portal_role', 'phone', 'faculty',
        'study_program_code', 'study_program', 'degree_level', 'entry_year', 'profile_loaded',
    ]);
    foreach ($forbidden as $key => $value) {
        expect($result)->not->toHaveKey($key)
            ->and(json_encode($result))->not->toContain($value);
    }
});
