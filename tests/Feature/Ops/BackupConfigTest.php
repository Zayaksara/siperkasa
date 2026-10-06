<?php

use Illuminate\Support\Env;
use Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumStorageInMegabytes;

function backupConfigWith(array $env): array
{
    $repository = Env::getRepository();
    foreach ($env as $key => $value) {
        $repository->set($key, $value);
    }

    try {
        return require config_path('backup.php');
    } finally {
        foreach (array_keys($env) as $key) {
            $repository->clear($key);
        }
    }
}

test('the database source is mysql', function () {
    expect(config('backup.backup.source.databases'))->toBe(['mysql']);
});

test('private storage is backed up and the backups folder is not', function () {
    expect(config('backup.backup.source.files.include'))->toContain(storage_path('app/private'))
        ->and(config('backup.backup.source.files.exclude'))->toContain(storage_path('app/backups'));
});

test('the backups disk is local and not public', function () {
    $disk = config('filesystems.disks.backups');

    expect($disk['driver'])->toBe('local')
        ->and($disk['root'])->toBe(storage_path('app/backups'))
        ->and($disk['root'])->not->toStartWith(public_path())
        ->and($disk['root'])->not->toContain('app'.DIRECTORY_SEPARATOR.'public')
        ->and($disk)->not->toHaveKey('url')
        ->and(config('backup.backup.destination.disks'))->toBe(['backups']);
});

const OFFSITE_ALL = [
    'BACKUP_OFFSITE_HOST' => 'backup.example.org',
    'BACKUP_OFFSITE_HOST_FINGERPRINT' => 'SHA256:abc123',
    'BACKUP_ARCHIVE_PASSWORD' => 'rahasia-panjang',
];

test('the offsite disk is a destination only when host, fingerprint and archive password are all set', function () {
    expect(backupConfigWith([])['backup']['destination']['disks'])->toBe(['backups'])
        ->and(backupConfigWith(OFFSITE_ALL)['backup']['destination']['disks'])->toBe(['backups', 'offsite'])
        ->and(config('filesystems.disks.offsite.driver'))->toBe('sftp');

    foreach (array_keys(OFFSITE_ALL) as $missing) {
        $partial = collect(OFFSITE_ALL)->except($missing)->all();

        expect(backupConfigWith($partial)['backup']['destination']['disks'])->toBe(['backups'], "without $missing")
            ->and(backupConfigWith($partial)['monitor_backups'][0]['disks'])->toBe(['backups'], "monitor without $missing");
    }
});

test('the offsite disk is monitored when all three are set', function () {
    expect(backupConfigWith(OFFSITE_ALL)['monitor_backups'][0]['disks'])->toBe(['backups', 'offsite']);
});

test('the offsite sftp disk verifies the host fingerprint', function () {
    $repository = Env::getRepository();
    $repository->set('BACKUP_OFFSITE_HOST_FINGERPRINT', 'SHA256:abc123');

    try {
        $disks = (require config_path('filesystems.php'))['disks'];
    } finally {
        $repository->clear('BACKUP_OFFSITE_HOST_FINGERPRINT');
    }

    expect($disks['offsite'])->toHaveKey('hostFingerprint')
        ->and($disks['offsite']['hostFingerprint'])->toBe('SHA256:abc123');
});

test('the failure alert mail has no fallback address', function () {
    expect(backupConfigWith(['BACKUP_NOTIFY_MAIL' => 'admin@example.ac.id', 'MAIL_FROM_ADDRESS' => 'noreply@example.ac.id'])['notifications']['mail']['to'])
        ->toBe('admin@example.ac.id')
        ->and(backupConfigWith(['MAIL_FROM_ADDRESS' => 'noreply@example.ac.id'])['notifications']['mail']['to'])
        ->toEndWith('.invalid');
});

test('the storage cap reads BACKUP_MAX_MB and is not a monitor health check', function () {
    expect(backupConfigWith(['BACKUP_MAX_MB' => '8000'])['cleanup']['default_strategy']['delete_oldest_backups_when_using_more_megabytes_than'])->toBe(8000)
        ->and(backupConfigWith([])['cleanup']['default_strategy']['delete_oldest_backups_when_using_more_megabytes_than'])->toBe(5000)
        ->and(array_keys(config('backup.monitor_backups.0.health_checks')))->not->toContain(MaximumStorageInMegabytes::class);
});

test('blank offsite credentials become null so key auth is not attempted with an empty key', function () {
    $repository = Env::getRepository();
    foreach (['BACKUP_OFFSITE_PRIVATE_KEY', 'BACKUP_OFFSITE_PASSPHRASE', 'BACKUP_OFFSITE_PASSWORD'] as $key) {
        $repository->set($key, '');
    }

    try {
        $disk = (require config_path('filesystems.php'))['disks']['offsite'];
    } finally {
        foreach (['BACKUP_OFFSITE_PRIVATE_KEY', 'BACKUP_OFFSITE_PASSPHRASE', 'BACKUP_OFFSITE_PASSWORD'] as $key) {
            $repository->clear($key);
        }
    }

    expect($disk['privateKey'])->toBeNull()->and($disk['passphrase'])->toBeNull()->and($disk['password'])->toBeNull();
});

test('retention is 3 days, 14 daily, 8 weekly and capped at 5000 MB', function () {
    $strategy = config('backup.cleanup.default_strategy');

    expect($strategy['keep_all_backups_for_days'])->toBe(3)
        ->and($strategy['keep_daily_backups_for_days'])->toBe(14)
        ->and($strategy['keep_weekly_backups_for_weeks'])->toBe(8)
        ->and($strategy['delete_oldest_backups_when_using_more_megabytes_than'])->toBe(5000);
});

test('only failures and unhealthy backups send mail', function () {
    $mailed = collect(config('backup.notifications.notifications'))
        ->filter(fn ($channels) => in_array('mail', $channels, true))
        ->keys()
        ->map(class_basename(...))
        ->sort()
        ->values()
        ->all();

    expect($mailed)->toBe([
        'BackupHasFailedNotification',
        'CleanupHasFailedNotification',
        'UnhealthyBackupWasFoundNotification',
    ]);
});

test('the mysqldump path comes from MYSQLDUMP_PATH', function () {
    expect(config('database.connections.mysql.dump.dump_binary_path'))->toBe(env('MYSQLDUMP_PATH', ''));
});
