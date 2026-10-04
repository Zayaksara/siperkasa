<?php

use App\Models\Report;
use App\Models\User;
use App\Notifications\ReportAssigned;
use App\Notifications\ReportStatusChanged;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->report = Report::factory()->for($this->user)->create();
});

function notifyTimes(User $user, Report $report, int $times): void
{
    foreach (range(1, $times) as $ignored) {
        $user->notify(new ReportStatusChanged($report));
    }
}

test('the badge count is shared with every page', function () {
    notifyTimes($this->user, $this->report, 3);

    $this->actingAs($this->user)->get(route('user.dashboard'))
        ->assertInertia(fn ($page) => $page->where('unreadNotificationsCount', 3));
});

test('the inbox lists only my notifications with the standard payload', function () {
    notifyTimes($this->user, $this->report, 3);
    notifyTimes(User::factory()->create(), Report::factory()->create(), 2);

    $this->actingAs($this->user)->get(route('notifications.index'))
        ->assertInertia(fn ($page) => $page
            ->component('notifications/Index')
            ->has('notifications.data', 3)
            ->where('notifications.data.0.read', false)
            ->where('notifications.data.0.ticket_number', $this->report->ticket_number)
            ->has('notifications.data.0.message')
            ->has('notifications.data.0.url')
            ->has('notifications.links'));
});

test('the inbox is paginated at 15', function () {
    notifyTimes($this->user, $this->report, 17);

    $this->actingAs($this->user)->get(route('notifications.index'))
        ->assertInertia(fn ($page) => $page->has('notifications.data', 15));
});

test('the recent endpoint returns at most 10 items as JSON', function () {
    notifyTimes($this->user, $this->report, 12);

    $this->actingAs($this->user)->getJson(route('notifications.recent'))
        ->assertOk()
        ->assertJsonCount(10, 'data')
        ->assertJsonStructure(['data' => [['id', 'report_id', 'ticket_number', 'status', 'message', 'url', 'read', 'created_at']]]);
});

test('the unread count endpoint counts only my unread notifications', function () {
    notifyTimes($this->user, $this->report, 3);
    notifyTimes(User::factory()->create(), Report::factory()->create(), 5);
    $this->user->notifications()->first()->markAsRead();

    $this->actingAs($this->user)->getJson(route('notifications.unread-count'))
        ->assertOk()
        ->assertExactJson(['count' => 2]);
});

test('reading a notification marks it read and opens its report', function () {
    $this->user->notify(new ReportStatusChanged($this->report));
    $notification = $this->user->notifications()->first();

    $this->actingAs($this->user)->post(route('notifications.read', $notification->id))
        ->assertRedirect(route('reports.show', $this->report, false));

    expect($notification->fresh()->read_at)->not->toBeNull();
    expect($this->user->unreadNotifications()->count())->toBe(0);
});

test('nobody can read someone else’s notification', function () {
    $this->user->notify(new ReportStatusChanged($this->report));
    $notification = $this->user->notifications()->first();

    $this->actingAs(User::factory()->create())->post(route('notifications.read', $notification->id))->assertNotFound();

    expect($notification->fresh()->read_at)->toBeNull();
});

test('mark all read clears the badge', function () {
    notifyTimes($this->user, $this->report, 4);

    $this->actingAs($this->user)->post(route('notifications.read-all'))->assertRedirect();

    expect($this->user->unreadNotifications()->count())->toBe(0);
});

test('reading the notification of a task that was reassigned marks it read and explains', function () {
    $pic = User::factory()->pic()->create();
    $report = Report::factory()->assigned($pic)->create();
    $pic->notify(new ReportAssigned($report));
    $notification = $pic->notifications()->first();

    $report->update(['assigned_pic_id' => User::factory()->pic()->create()->id]);

    $this->actingAs($pic)->post(route('notifications.read', $notification->id))
        ->assertRedirect(route('pic.tasks.index'))
        ->assertInertiaFlash('toast', ['type' => 'error', 'message' => 'Laporan tidak dapat dibuka.']);

    expect($notification->fresh()->read_at)->not->toBeNull();
});

test('a stored url that leaves the site redirects to the dashboard', function (string $url) {
    $this->user->notify(new ReportStatusChanged($this->report));
    $notification = $this->user->notifications()->first();
    $notification->update(['data' => [...$notification->data, 'url' => $url]]);

    $this->actingAs($this->user)->post(route('notifications.read', $notification->id))
        ->assertRedirect(route('dashboard'));

    expect($notification->fresh()->read_at)->not->toBeNull();
})->with([
    'absolute' => 'https://evil.example/x',
    'protocol relative' => '//evil.example',
    'backslash' => '/\evil.example',
    'tab' => '/'.chr(9).'/evil.example',
    'carriage return' => '/'.chr(13).'/evil.example',
    'newline' => '/'.chr(10).'/evil.example',
    'missing' => '',
]);

test('guests are sent to the login page', function () {
    $this->get(route('notifications.index'))->assertRedirect(route('login'));
    $this->getJson(route('notifications.recent'))->assertUnauthorized();
    $this->getJson(route('notifications.unread-count'))->assertUnauthorized();
});
