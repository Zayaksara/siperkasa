<?php

use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Facades\Date;

beforeEach(function () {
    Date::setTestNow('2026-11-09 12:00:00');
    $this->admin = User::factory()->admin()->create();
});

function slaReport(User $pic, string $state, array $attrs = []): Report
{
    return Report::factory()->{$state}($pic)->create(['validated_at' => '2026-11-05 09:00:00', ...$attrs]);
}

function performance($test, array $query = [])
{
    return $test->actingAs($test->admin)->get(route('admin.pic-performance', $query));
}

it('classifies tasks per pic', function () {
    $pic = User::factory()->pic()->create(['name' => 'Andi']);
    slaReport($pic, 'closed', ['sla_date' => '2026-11-07 12:00:00', 'closed_at' => '2026-11-07 10:00:00']);
    slaReport($pic, 'closed', ['sla_date' => '2026-11-07 12:00:00', 'closed_at' => '2026-11-07 17:00:00']);
    slaReport($pic, 'onHold', ['sla_date' => '2026-11-08 12:00:00']);
    slaReport($pic, 'inProgress', ['sla_date' => '2026-11-20 12:00:00']);

    performance($this)->assertInertia(fn ($page) => $page
        ->component('admin/pic-performance/Index')
        ->where('performance.pics.0.id', $pic->id)
        ->where('performance.pics.0.total', 4)
        ->where('performance.pics.0.on_time', 1)
        ->where('performance.pics.0.late_closed', 1)
        ->where('performance.pics.0.late_open', 1)
        ->where('performance.pics.0.on_track', 1)
        ->where('performance.pics.0.on_time_rate', 33.3)
        ->where('performance.pics.0.avg_late_hours', 5)
        ->where('performance.summary.total', 4)
        ->where('range.to', '2026-11-09'));
});

it('excludes cancelled rejected pending and reports without sla', function () {
    $pic = User::factory()->pic()->create();
    slaReport($pic, 'inProgress', ['sla_date' => '2026-11-20 12:00:00']);
    slaReport($pic, 'cancelled', ['assigned_pic_id' => $pic->id, 'sla_date' => '2026-11-20 12:00:00']);
    slaReport($pic, 'rejected', ['assigned_pic_id' => $pic->id, 'sla_date' => '2026-11-20 12:00:00']);
    slaReport($pic, 'pending', ['assigned_pic_id' => $pic->id, 'sla_date' => '2026-11-20 12:00:00']);
    slaReport($pic, 'inProgress', ['sla_date' => null]);

    performance($this)->assertInertia(fn ($page) => $page
        ->where('performance.pics.0.total', 1)
        ->where('performance.summary.total', 1));
});

it('returns null rate when pic has only on track tasks', function () {
    $pic = User::factory()->pic()->create();
    slaReport($pic, 'assigned', ['sla_date' => '2026-11-20 12:00:00']);

    performance($this)->assertInertia(fn ($page) => $page
        ->where('performance.pics.0.on_track', 1)
        ->where('performance.pics.0.on_time_rate', null)
        ->where('performance.pics.0.avg_late_hours', null));
});

it('filters by validated_at range', function () {
    $pic = User::factory()->pic()->create();
    slaReport($pic, 'inProgress', ['sla_date' => '2026-11-20 12:00:00']);
    slaReport($pic, 'inProgress', ['sla_date' => '2026-11-20 12:00:00', 'validated_at' => '2026-09-01 09:00:00']);

    performance($this)->assertInertia(fn ($page) => $page->where('performance.pics.0.total', 1));
    performance($this, ['from' => '2026-09-01', 'to' => '2026-09-01'])
        ->assertInertia(fn ($page) => $page->where('performance.pics.0.total', 1));
    performance($this, ['from' => '2026-08-01', 'to' => '2026-08-02'])
        ->assertInertia(fn ($page) => $page->has('performance.pics', 0));
});

it('includes trashed pic and marks it', function () {
    $pic = User::factory()->pic()->create();
    slaReport($pic, 'inProgress', ['sla_date' => '2026-11-20 12:00:00']);
    $pic->delete();

    performance($this)->assertInertia(fn ($page) => $page
        ->where('performance.pics.0.id', $pic->id)
        ->where('performance.pics.0.trashed', true));
});

it('sorts worst rate first', function () {
    $good = User::factory()->pic()->create(['name' => 'Baik']);
    $bad = User::factory()->pic()->create(['name' => 'Buruk']);
    $none = User::factory()->pic()->create(['name' => 'Aaa']);
    slaReport($good, 'closed', ['sla_date' => '2026-11-07 12:00:00', 'closed_at' => '2026-11-07 10:00:00']);
    slaReport($bad, 'onHold', ['sla_date' => '2026-11-08 12:00:00']);
    slaReport($none, 'assigned', ['sla_date' => '2026-11-20 12:00:00']);

    performance($this)->assertInertia(fn ($page) => $page
        ->where('performance.pics.0.id', $bad->id)
        ->where('performance.pics.1.id', $good->id)
        ->where('performance.pics.2.id', $none->id));
});

it('rejects ranges over a year', function () {
    performance($this, ['from' => '2024-01-01', 'to' => '2026-01-01'])->assertSessionHasErrors('to');
});

it('forbids non admin', function (string $state) {
    $this->actingAs(User::factory()->{$state}()->create())
        ->get(route('admin.pic-performance'))->assertForbidden();
})->with(['pic', 'reporter']);
