<?php

use App\Models\Building;
use App\Models\Category;
use App\Models\Report;
use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->travelTo(Carbon::parse('2026-10-14 12:00', 'Asia/Jakarta')); // a Wednesday
});

test('counts per status, waiting validation and overdue', function () {
    Report::factory()->count(2)->create();
    Report::factory()->assigned()->create(['sla_date' => now()->subDay()]);
    Report::factory()->closed()->create();
    Report::factory()->rejected()->create();

    $this->actingAs($this->admin)->get(route('admin.dashboard'))
        ->assertInertia(fn ($page) => $page
            ->component('admin/Dashboard')
            ->where('stats.counts.Pending', 2)
            ->where('stats.counts.Assigned', 1)
            ->where('stats.counts.Closed', 1)
            ->where('stats.counts.Rejected', 1)
            ->where('stats.counts.Cancelled', 0)
            ->where('stats.total', 5)
            ->where('stats.waiting_validation', 2)
            ->where('stats.overdue', 1)
            ->where('range.from', '2026-09-15')
            ->where('range.to', '2026-10-14'));
});

test('the average completion time is the mean of closed reports', function () {
    $now = now();

    foreach ([1, 2, 3] as $days) {
        Report::factory()->closed()->create([
            'created_at' => $now->copy()->subDays($days),
            'validated_at' => $now->copy()->subDays($days)->addHours(6),
            'closed_at' => $now,
        ]);
    }

    $this->actingAs($this->admin)->get(route('admin.dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('stats.avg_completion_hours', fn ($v) => is_numeric($v) && (float) $v === 48.0)
            ->where('stats.avg_validation_hours', fn ($v) => is_numeric($v) && (float) $v === 6.0));
});

test('averages are null when there is nothing to average', function () {
    Report::factory()->create();

    $this->actingAs($this->admin)->get(route('admin.dashboard'))
        ->assertInertia(fn ($page) => $page->where('stats.avg_completion_hours', null)->where('stats.avg_validation_hours', null));
});

test('an empty database shows zeros, empty lists and a 12-week trend', function () {
    $this->actingAs($this->admin)->get(route('admin.dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('stats.total', 0)
            ->where('stats.overdue', 0)
            ->has('stats.by_category', 0)
            ->has('stats.by_building', 0)
            ->has('stats.trend', 12)
            ->where('stats.trend.11.created', 0));
});

test('the date filter narrows every count and includes the whole last day', function () {
    Report::factory()->create(['created_at' => '2026-10-10 09:00:00']);
    Report::factory()->create(['created_at' => '2026-10-14 23:30:00']);   // last minute of the range
    Report::factory()->create(['created_at' => '2026-10-05 09:00:00']);   // outside a 7-day window
    Report::factory()->create(['created_at' => '2026-10-15 00:30:00']);   // the day after

    $this->actingAs($this->admin)->get(route('admin.dashboard', ['from' => '2026-10-08', 'to' => '2026-10-14']))
        ->assertInertia(fn ($page) => $page->where('stats.total', 2)->where('range.from', '2026-10-08'));
});

test('an invalid range is a validation error', function () {
    $this->actingAs($this->admin)->get(route('admin.dashboard', ['from' => '2026-10-14', 'to' => '2026-10-01']))
        ->assertSessionHasErrors('to');

    expect(session('errors')->first('to'))->toContain('Tanggal akhir');
});

test('reports are grouped by category and building with ids for drill-down', function () {
    $category = Category::factory()->create(['name' => 'Elektronik']);
    $building = Building::factory()->create(['name' => 'Gedung A']);
    $room = Room::factory()->for($building)->create();

    Report::factory()->count(3)->create(['category_id' => $category->id, 'room_id' => $room->id]);
    Report::factory()->create();

    $this->actingAs($this->admin)->get(route('admin.dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('stats.by_category.0.id', $category->id)
            ->where('stats.by_category.0.name', 'Elektronik')
            ->where('stats.by_category.0.total', 3)
            ->where('stats.by_building.0.id', $building->id)
            ->where('stats.by_building.0.total', 3));
});

test('the weekly trend counts reports created and closed per ISO week', function () {
    Report::factory()->create(['created_at' => '2026-10-07 10:00:00']);                       // week of 5 Oct
    Report::factory()->closed()->create(['created_at' => '2026-10-13 10:00:00', 'closed_at' => '2026-10-13 15:00:00']); // week of 12 Oct

    $this->actingAs($this->admin)->get(route('admin.dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('stats.trend.10.start', '2026-10-05')
            ->where('stats.trend.10.created', 1)
            ->where('stats.trend.11.start', '2026-10-12')
            ->where('stats.trend.11.created', 1)
            ->where('stats.trend.11.closed', 1));
});

test('the weekly trend buckets reports correctly across the ISO year boundary', function () {
    $this->travelTo(Carbon::parse('2027-01-05 12:00', 'Asia/Jakarta')); // ISO week 1 of 2027
    Report::factory()->create(['created_at' => '2026-12-31 10:00:00']);   // ISO 2026-W53
    Report::factory()->create(['created_at' => '2027-01-03 23:00:00']);   // Sunday, still 2026-W53
    Report::factory()->closed()->create(['created_at' => '2026-12-01 10:00:00', 'closed_at' => '2027-01-04 08:00:00']); // 2027-W01

    $this->actingAs($this->admin)->get(route('admin.dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('stats.trend.9.created', 0)
            ->where('stats.trend.10.start', '2026-12-28')
            ->where('stats.trend.10.created', 2)
            ->where('stats.trend.10.closed', 0)
            ->where('stats.trend.11.start', '2027-01-04')
            ->where('stats.trend.11.created', 0)
            ->where('stats.trend.11.closed', 1));
});

test('pic load lists active tasks per active pic', function () {
    $busy = User::factory()->pic()->create(['name' => 'Pak Sibuk']);
    User::factory()->pic()->inactive()->create();
    Report::factory()->count(2)->inProgress($busy)->create();
    Report::factory()->closed($busy)->create();

    $this->actingAs($this->admin)->get(route('admin.dashboard'))
        ->assertInertia(fn ($page) => $page
            ->has('stats.pic_load', 1)
            ->where('stats.pic_load.0.name', 'Pak Sibuk')
            ->where('stats.pic_load.0.active_tasks', 2));
});

test('statistics are cached for five minutes', function () {
    Report::factory()->create();
    $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertInertia(fn ($page) => $page->where('stats.total', 1));

    Report::factory()->create();
    $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertInertia(fn ($page) => $page->where('stats.total', 1));

    $this->travelTo(now()->addMinutes(6));
    $this->actingAs($this->admin)->get(route('admin.dashboard', ['to' => now()->toDateString()]))->assertInertia(fn ($page) => $page->where('stats.total', 2));
});

test('only admins see admin statistics', function (string $state) {
    $this->actingAs(User::factory()->{$state}()->create())->get(route('admin.dashboard'))->assertForbidden();
})->with(['reporter', 'pic']);
