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

    expect((new BridgeClient)->authenticate('u1', 'p'))->toBe([
        'username' => 'u1',
        'name' => 'Siti',
        'email' => null,
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
