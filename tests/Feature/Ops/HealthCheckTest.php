<?php

use App\Providers\AppServiceProvider;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

test('/up returns 200 when the database and disk are fine', function () {
    $this->get('/up')->assertOk();
});

test('/up returns 500 when free disk space is below the threshold', function () {
    config(['siperkasa.health_min_free_mb' => PHP_INT_MAX]);

    $this->get('/up')->assertStatus(500);
});

test('/up returns 500 when the database is down', function () {
    DB::shouldReceive('select')->andThrow(new PDOException('Connection refused'));

    $this->get('/up')->assertStatus(500);
});

test('trusted proxies config is honored for the client ip', function () {
    Route::get('/_who', fn (Request $request) => $request->ip());
    $headers = ['X-Forwarded-For' => '203.0.113.9'];
    $server = ['REMOTE_ADDR' => '10.0.0.5'];

    $apply = function (?string $value): void {
        config(['siperkasa.trusted_proxies' => $value]);
        $provider = app()->getProvider(AppServiceProvider::class);
        (fn () => $this->configureTrustedProxies())->call($provider);
    };

    try {
        $apply(null);
        $this->withServerVariables($server)->get('/_who', $headers)->assertContent('10.0.0.5');

        $apply('10.0.0.5');
        $this->withServerVariables($server)->get('/_who', $headers)->assertContent('203.0.113.9');

        $apply('10.9.9.9');
        $this->withServerVariables($server)->get('/_who', $headers)->assertContent('10.0.0.5');
    } finally {
        TrustProxies::at([]);
    }
});
