<?php

use App\Enums\ReportStatus;
use App\Models\Category;
use App\Models\Report;
use App\Models\Room;
use App\Models\User;

test('visibleTo scopes reports per role', function () {
    $reporter = User::factory()->create();
    $pic = User::factory()->pic()->create();
    $admin = User::factory()->admin()->create();

    $mine = Report::factory()->for($reporter)->create();
    $assigned = Report::factory()->assigned($pic)->create();
    Report::factory()->create();

    expect(Report::visibleTo($reporter)->pluck('id')->all())->toBe([$mine->id]);
    expect(Report::visibleTo($pic)->pluck('id')->all())->toBe([$assigned->id]);
    expect(Report::visibleTo($admin)->count())->toBe(3);
});

test('overdue only counts non-final reports past their SLA', function () {
    $late = Report::factory()->assigned()->create(['sla_date' => now()->subDay()]);
    Report::factory()->assigned()->create(['sla_date' => now()->addDay()]);
    Report::factory()->closed()->create(['sla_date' => now()->subDay()]);
    Report::factory()->create(['sla_date' => null]);

    expect(Report::overdue()->pluck('id')->all())->toBe([$late->id]);
    expect($late->isOverdue())->toBeTrue();
});

test('categories and rooms used by a report are protected from deletion', function () {
    $report = Report::factory()->create();

    expect($report->category->isInUse())->toBeTrue()
        ->and($report->room->isInUse())->toBeTrue()
        ->and(Category::factory()->create()->isInUse())->toBeFalse()
        ->and(Room::factory()->create()->isInUse())->toBeFalse();
});

test('factory states produce the matching status', function (string $state, ReportStatus $status) {
    expect(Report::factory()->{$state}()->create()->status)->toBe($status);
})->with([
    ['pending', ReportStatus::Pending],
    ['assigned', ReportStatus::Assigned],
    ['inProgress', ReportStatus::InProgress],
    ['onHold', ReportStatus::OnHold],
    ['closed', ReportStatus::Closed],
    ['rejected', ReportStatus::Rejected],
    ['cancelled', ReportStatus::Cancelled],
]);
