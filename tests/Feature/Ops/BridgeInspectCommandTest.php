<?php

use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.bridge.base_url' => 'https://bridge.test']);
    config(['services.bridge.timeout' => 10]);
    app()['env'] = 'local';
    Http::preventStrayRequests();
});

test('it refuses to run outside local environment', function () {
    app()['env'] = 'production';

    Http::fake();

    $this->artisan('bridge:inspect')
        ->expectsOutput('Hanya untuk environment local.')
        ->assertFailed();

    Http::assertNothingSent();
});

test('it fails when base url is blank', function () {
    config(['services.bridge.base_url' => '']);

    Http::fake();

    $this->artisan('bridge:inspect')
        ->expectsOutput('BRIDGE_API_BASE_URL belum diisi.')
        ->assertFailed();

    Http::assertNothingSent();
});

test('it prints field names and types only, never values, and logs out', function () {
    Http::fake([
        'https://bridge.test/api/v2/portal/login' => Http::response([
            'success' => true,
            'data' => [
                'token' => 'tok-123',
                'user' => ['nama' => 'Budi', 'roles' => ['mahasiswa']],
            ],
        ], 200),
        'https://bridge.test/api/v2/profil' => Http::response([
            'data' => [
                'nama' => 'Budi',
                'prodi' => ['kode' => 'TI', 'nama' => 'Teknik'],
                'angkatan' => 2021,
                'aktif' => true,
                'foto' => null,
            ],
        ], 200),
        'https://bridge.test/api/v2/logout' => Http::response([], 200),
    ]);

    $this->artisan('bridge:inspect')
        ->expectsQuestion('NIM/NIP', '2101010001')
        ->expectsQuestion('Password portal', 'rahasia-portal')
        ->expectsOutputToContain('Login: HTTP 200')
        ->expectsOutputToContain('data.user.nama: string')
        ->expectsOutputToContain('data.user.roles[] (list, 1 item)')
        ->expectsOutputToContain('data.user.roles[]: string')
        ->expectsOutputToContain('Profil: HTTP 200')
        ->expectsOutputToContain('data.prodi.kode: string')
        ->expectsOutputToContain('data.angkatan: int')
        ->expectsOutputToContain('data.aktif: bool')
        ->expectsOutputToContain('data.foto: null')
        ->assertSuccessful();

    Http::assertSent(function ($request) {
        return str_ends_with($request->url(), '/api/v2/logout')
            && $request->hasHeader('Authorization', 'Bearer tok-123');
    });
});

test('output never contains token or portal values', function () {
    Http::fake([
        'https://bridge.test/api/v2/portal/login' => Http::response([
            'success' => true,
            'data' => [
                'token' => 'tok-123',
                'user' => ['nama' => 'Budi', 'roles' => ['mahasiswa']],
            ],
        ], 200),
        'https://bridge.test/api/v2/profil' => Http::response([
            'data' => [
                'nama' => 'Budi',
                'prodi' => ['kode' => 'TI', 'nama' => 'Teknik'],
                'angkatan' => 2021,
                'aktif' => true,
                'foto' => null,
            ],
        ], 200),
        'https://bridge.test/api/v2/logout' => Http::response([], 200),
    ]);

    $this->artisan('bridge:inspect')
        ->expectsQuestion('NIM/NIP', '2101010001')
        ->expectsQuestion('Password portal', 'rahasia-portal')
        ->doesntExpectOutputToContain('tok-123')
        ->doesntExpectOutputToContain('Budi')
        ->doesntExpectOutputToContain('Teknik')
        ->doesntExpectOutputToContain('rahasia-portal')
        ->doesntExpectOutputToContain('token')
        ->assertSuccessful();
});

test('it fails when login is rejected', function () {
    Http::fake([
        'https://bridge.test/api/v2/portal/login' => Http::response(['success' => false, 'message' => 'salah'], 401),
    ]);

    $this->artisan('bridge:inspect')
        ->expectsQuestion('NIM/NIP', '2101010001')
        ->expectsQuestion('Password portal', 'salah-banget')
        ->expectsOutputToContain('Login: HTTP 401')
        ->assertFailed();

    Http::assertSentCount(1);
});
