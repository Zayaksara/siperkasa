<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function notificationRow(User $user, ?int $readDaysAgo): string
{
    $id = (string) Str::uuid();

    DB::table('notifications')->insert([
        'id' => $id,
        'type' => 'App\\Notifications\\ReportStatusChanged',
        'notifiable_type' => $user->getMorphClass(),
        'notifiable_id' => $user->id,
        'data' => '{}',
        'read_at' => $readDaysAgo === null ? null : now()->subDays($readDaysAgo),
        'created_at' => now()->subDays(200),
        'updated_at' => now()->subDays(200),
    ]);

    return $id;
}

test('it deletes old read notifications and keeps unread and recent ones', function () {
    $user = User::factory()->create();
    $oldRead = notificationRow($user, 200);
    $oldUnread = notificationRow($user, null);
    $recentRead = notificationRow($user, 10);

    $this->artisan('notifications:prune')->assertSuccessful();

    expect(DB::table('notifications')->pluck('id')->all())
        ->toContain($oldUnread, $recentRead)
        ->not->toContain($oldRead);
});

test('--days sets the boundary', function () {
    $user = User::factory()->create();
    $ten = notificationRow($user, 10);
    $thirtyOne = notificationRow($user, 31);
    $unread = notificationRow($user, null);

    $this->artisan('notifications:prune', ['--days' => 30])->assertSuccessful();

    expect(DB::table('notifications')->pluck('id')->all())
        ->toContain($ten, $unread)
        ->not->toContain($thirtyOne);
});
