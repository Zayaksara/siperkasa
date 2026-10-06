<?php

function productionEnv(): array
{
    $values = [];
    foreach (file(base_path('.env.production.example'), FILE_IGNORE_NEW_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $values[$key] = trim($value, ' "\'');
    }

    return $values;
}

test('the production example holds the safe production values', function () {
    $env = productionEnv();

    expect($env)->toMatchArray([
        'APP_ENV' => 'production',
        'APP_DEBUG' => 'false',
        'DB_CONNECTION' => 'mysql',
        'LOG_STACK' => 'daily',
        'LOG_DAILY_DAYS' => '14',
        'LOG_LEVEL' => 'warning',
        'SESSION_SECURE_COOKIE' => 'true',
        'QUEUE_CONNECTION' => 'database',
        'CACHE_STORE' => 'database',
        'INERTIA_SSR_ENABLED' => 'false',
        'APP_KEY' => '',
    ])->and($env['APP_URL'])->toStartWith('https://');
});

test('the production example holds no secrets', function () {
    $env = productionEnv();

    foreach (['DB_PASSWORD', 'SUPER_ADMIN_PASSWORD', 'MAIL_PASSWORD', 'BACKUP_OFFSITE_PASSWORD', 'BACKUP_ARCHIVE_PASSWORD'] as $key) {
        expect($env[$key] ?? '')->toBe('');
    }
});

test('SSR is off under testing', function () {
    expect(config('inertia.ssr.enabled'))->toBeFalse();
});

test('the production example lists the required backup keys', function () {
    $content = file_get_contents(base_path('.env.production.example'));

    foreach (['BACKUP_NOTIFY_MAIL', 'BACKUP_OFFSITE_HOST_FINGERPRINT', 'BACKUP_ARCHIVE_PASSWORD', 'BACKUP_MAX_MB', 'HEALTH_CHECK_BACKUP'] as $key) {
        expect(productionEnv())->toHaveKey($key);
    }

    expect($content)->toMatch('/WAJIB[^
]*
(?:#[^
]*
)*BACKUP_NOTIFY_MAIL=/');
});

test('the production example turns the backup health check on', function () {
    expect(productionEnv()['HEALTH_CHECK_BACKUP'])->toBe('true');
});
