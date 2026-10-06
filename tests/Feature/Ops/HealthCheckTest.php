<?php

use App\Providers\AppServiceProvider;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

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

function productionBackupHealth(?int $backupAgeHours, string $mail = 'admin@kampus.ac.id', bool $toggle = true): void
{
    app()['env'] = 'production';
    config([
        'siperkasa.health_check_backup' => $toggle,
        'backup.notifications.mail.to' => $mail,
    ]);
    Storage::fake('backups');

    if ($backupAgeHours !== null) {
        $path = config('backup.backup.name').'/2026-10-06-01-30-00.zip';
        Storage::disk('backups')->put($path, 'zip');
        touch(Storage::disk('backups')->path($path), now()->subHours($backupAgeHours)->getTimestamp());
    }
}

test('/up returns 500 in production when no backup exists', function () {
    productionBackupHealth(null);

    $this->get('/up')->assertStatus(500);
});

test('/up returns 500 in production when the newest backup is older than 26 hours', function () {
    productionBackupHealth(27);

    $this->get('/up')->assertStatus(500);
});

test('/up returns 200 in production when the newest backup is fresh', function () {
    productionBackupHealth(2);

    $this->get('/up')->assertOk();
});

test('/up returns 500 in production while the backup mail is the placeholder', function () {
    productionBackupHealth(2, 'backup-notify-not-set@example.invalid');

    $this->get('/up')->assertStatus(500);
});

test('/up skips the backup checks when HEALTH_CHECK_BACKUP is off', function () {
    productionBackupHealth(null, 'backup-notify-not-set@example.invalid', toggle: false);

    $this->get('/up')->assertOk();
});

test('/up skips the backup checks outside production', function () {
    productionBackupHealth(null, 'backup-notify-not-set@example.invalid');
    app()['env'] = 'testing';

    $this->get('/up')->assertOk();
});
