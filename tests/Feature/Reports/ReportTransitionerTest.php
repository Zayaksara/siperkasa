<?php

use App\Actions\Reports\InvalidTransition;
use App\Actions\Reports\ReportTransitioner;
use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\User;

test('a valid transition updates the report and writes one log row', function () {
    $actor = User::factory()->admin()->create();
    $report = Report::factory()->create();

    app(ReportTransitioner::class)->transition($report, ReportStatus::Rejected, $actor, 'Duplikat', [
        'rejection_reason' => 'Duplikat',
        'admin_id' => $actor->id,
    ]);

    $report->refresh();
    expect($report->status)->toBe(ReportStatus::Rejected)
        ->and($report->rejection_reason)->toBe('Duplikat');

    $log = $report->statusLogs()->sole();
    expect($log->from_status)->toBe('Pending')
        ->and($log->to_status)->toBe('Rejected')
        ->and($log->actor_id)->toBe($actor->id)
        ->and($log->note)->toBe('Duplikat');
});

test('an invalid transition throws and changes nothing', function () {
    $report = Report::factory()->closed()->create();

    expect(fn () => app(ReportTransitioner::class)->transition($report, ReportStatus::InProgress, null))
        ->toThrow(InvalidTransition::class);

    expect($report->fresh()->status)->toBe(ReportStatus::Closed)
        ->and($report->statusLogs()->count())->toBe(0);
});

test('staying in the same status is not a transition', function () {
    $report = Report::factory()->create();

    expect(fn () => app(ReportTransitioner::class)->transition($report, ReportStatus::Pending, null))
        ->toThrow(InvalidTransition::class);
});

test('note() writes a same-status log row', function () {
    $user = User::factory()->create();
    $report = Report::factory()->for($user)->create();

    app(ReportTransitioner::class)->note($report, $user, 'Laporan diubah');

    $log = $report->statusLogs()->sole();
    expect($log->from_status)->toBe('Pending')->and($log->to_status)->toBe('Pending')->and($log->note)->toBe('Laporan diubah');
});

test('logCreated() records the creation with no previous status', function () {
    $user = User::factory()->create();
    $report = Report::factory()->for($user)->create();

    app(ReportTransitioner::class)->logCreated($report, $user);

    $log = $report->statusLogs()->sole();
    expect($log->from_status)->toBeNull()->and($log->to_status)->toBe('Pending');
});

test('stale in-memory model cannot transition from old status', function () {
    $actor = User::factory()->admin()->create();
    $report = Report::factory()->create(['status' => ReportStatus::Pending]);

    // Simulate: form loaded, DB changed while form open, then submit attempted
    $stale = Report::find($report->id);
    Report::whereKey($report->id)->update(['status' => ReportStatus::Rejected]);

    // Now stale model shows Pending but DB shows Rejected
    expect($stale->status)->toBe(ReportStatus::Pending);

    // Attempting Pending → Assigned should fail because DB is already Rejected
    expect(fn () => app(ReportTransitioner::class)->transition($stale, ReportStatus::Assigned, $actor))
        ->toThrow(InvalidTransition::class);

    // DB unchanged
    expect(Report::find($report->id)->status)->toBe(ReportStatus::Rejected)
        ->and($report->statusLogs()->count())->toBe(0);
});

test('deleted row throws InvalidTransition', function () {
    $actor = User::factory()->admin()->create();
    $report = Report::factory()->create(['status' => ReportStatus::Pending]);

    Report::whereKey($report->id)->delete();

    expect(fn () => app(ReportTransitioner::class)->transition($report, ReportStatus::Assigned, $actor))
        ->toThrow(InvalidTransition::class);
});
