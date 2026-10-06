<?php

use App\Jobs\ImportAssetsJob;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

function scheduled(string $command): ?Event
{
    foreach (app(Schedule::class)->events() as $event) {
        if (str_contains((string) $event->command, $command)) {
            return $event;
        }
    }

    return null;
}

function scheduledIndex(string $command): int|false
{
    foreach (app(Schedule::class)->events() as $i => $event) {
        if (str_contains((string) $event->command, $command)) {
            return $i;
        }
    }

    return false;
}

test('every expected command is scheduled', function (string $command) {
    expect(scheduled($command))->not->toBeNull();
})->with([
    'queue:work',
    'backup:clean',
    'backup:run',
    'backup:monitor',
    'notifications:prune --days=180',
    'queue:prune-failed --hours=720',
    'auth:clear-resets',
]);

test('the queue worker drains the queue every minute without a stale mutex', function () {
    $event = scheduled('queue:work');

    expect($event->command)->toContain('--stop-when-empty')
        ->and($event->command)->toContain('--max-time=840')
        ->and($event->expression)->toBe('* * * * *')
        ->and($event->withoutOverlapping)->toBeTrue()
        ->and($event->expiresAt)->toBe(15)
        ->and($event->runInBackground)->toBeTrue();
});

test('backup:run runs after backup:clean', function () {
    expect(scheduled('backup:clean')->expression)->toBe('0 1 * * *')
        ->and(scheduled('backup:run')->expression)->toBe('30 1 * * *')
        ->and(scheduledIndex('backup:run'))->toBeGreaterThan(scheduledIndex('backup:clean'));
});

test('import job timeout is below queue max-time, which is below the mutex', function () {
    $event = scheduled('queue:work');
    preg_match('/--max-time=(\d+)/', (string) $event->command, $m);
    $maxTime = (int) $m[1];
    $timeout = (new ImportAssetsJob(1, 'x'))->timeout;

    expect($timeout)->toBeLessThan($maxTime)
        ->and($maxTime)->toBeLessThan($event->expiresAt * 60)
        ->and($timeout)->toBeLessThan(config('queue.connections.database.retry_after'));
});
