<?php

use App\Actions\Reports\CancelReport;
use App\Actions\Reports\InvalidTransition;
use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\User;

test('the owner cancels a pending report', function () {
    $owner = User::factory()->create();
    $report = Report::factory()->for($owner)->create();

    $this->actingAs($owner)->post(route('reports.cancel', $report))->assertRedirect(route('reports.show', $report));

    $report->refresh();
    expect($report->status)->toBe(ReportStatus::Cancelled)
        ->and($report->cancelled_at)->not->toBeNull();

    $log = $report->statusLogs()->sole();
    expect($log->from_status)->toBe('Pending')->and($log->to_status)->toBe('Cancelled')->and($log->actor_id)->toBe($owner->id);
});

test('cancelling twice is refused', function () {
    $owner = User::factory()->create();
    $report = Report::factory()->for($owner)->create();

    $this->actingAs($owner)->post(route('reports.cancel', $report));
    $this->actingAs($owner)->post(route('reports.cancel', $report))->assertForbidden();

    expect($report->statusLogs()->count())->toBe(1);
});

test('a report that is already being processed can not be cancelled', function () {
    $owner = User::factory()->create();
    $report = Report::factory()->for($owner)->assigned()->create();

    $this->actingAs($owner)->post(route('reports.cancel', $report))->assertForbidden();

    expect($report->fresh()->status)->toBe(ReportStatus::Assigned);
});

test('a stale page cancel after the status changed is refused and changes nothing', function () {
    $owner = User::factory()->create();
    $report = Report::factory()->for($owner)->create();
    $report->update(['status' => ReportStatus::Assigned]); // changed after the page opened

    $this->actingAs($owner)->post(route('reports.cancel', $report))->assertForbidden();

    expect($report->fresh()->status)->toBe(ReportStatus::Assigned)
        ->and($report->fresh()->cancelled_at)->toBeNull()
        ->and($report->statusLogs()->count())->toBe(0);
});

test('the action refuses a report that left pending after it was loaded', function () {
    $owner = User::factory()->create();
    $report = Report::factory()->for($owner)->create();
    $loaded = Report::findOrFail($report->id);
    Report::whereKey($report->id)->update(['status' => ReportStatus::Assigned]);

    expect(fn () => app(CancelReport::class)->handle($loaded, $owner))->toThrow(InvalidTransition::class);

    expect($report->fresh()->status)->toBe(ReportStatus::Assigned)
        ->and($report->statusLogs()->count())->toBe(0);
});

test('nobody but the owner can cancel', function () {
    $report = Report::factory()->create();

    $this->actingAs(User::factory()->create())->post(route('reports.cancel', $report))->assertForbidden();
    $this->actingAs(User::factory()->admin()->create())->post(route('reports.cancel', $report))->assertForbidden();
});
