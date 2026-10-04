<?php

use App\Actions\Reports\HoldReport;
use App\Actions\Reports\InvalidTransition;
use App\Actions\Reports\ResumeReport;
use App\Actions\Reports\StartReport;
use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\User;
use App\Notifications\ReportStatusChanged;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->pic = User::factory()->pic()->create();
});

test('starting a task sets In_Progress, started_at and notifies the reporter', function () {
    $report = Report::factory()->assigned($this->pic)->create();

    $this->actingAs($this->pic)->post(route('pic.tasks.start', $report))->assertRedirect(route('pic.tasks.show', $report));

    $report->refresh();
    expect($report->status)->toBe(ReportStatus::InProgress)->and($report->started_at)->not->toBeNull();
    expect($report->statusLogs()->sole()->to_status)->toBe('In_Progress');

    Notification::assertSentTo($report->user, ReportStatusChanged::class, fn ($n) => $n->report->status === ReportStatus::InProgress);
});

test('holding needs a reason of 10+ characters and is logged each time', function () {
    $report = Report::factory()->inProgress($this->pic)->create();

    $this->actingAs($this->pic)->post(route('pic.tasks.hold', $report), [])->assertSessionHasErrors('hold_reason');
    $this->actingAs($this->pic)->post(route('pic.tasks.hold', $report), ['hold_reason' => 'pendek'])
        ->assertSessionHasErrors(['hold_reason' => 'Alasan penundaan minimal 10 karakter.']);
    expect($report->fresh()->status)->toBe(ReportStatus::InProgress);

    $this->actingAs($this->pic)->post(route('pic.tasks.hold', $report), ['hold_reason' => 'Menunggu sparepart datang.'])->assertSessionHasNoErrors();
    expect($report->fresh()->status)->toBe(ReportStatus::OnHold)->and($report->fresh()->hold_reason)->toBe('Menunggu sparepart datang.');
    Notification::assertSentTo($report->user, ReportStatusChanged::class, fn ($n) => $n->report->status === ReportStatus::OnHold);

    $this->actingAs($this->pic)->post(route('pic.tasks.resume', $report))->assertSessionHasNoErrors();
    $this->actingAs($this->pic)->post(route('pic.tasks.hold', $report), ['hold_reason' => 'Menunggu persetujuan biaya.']);

    $notes = $report->statusLogs()->pluck('note')->all();
    expect($notes)->toContain('Menunggu sparepart datang.')->toContain('Menunggu persetujuan biaya.');
});

test('resuming returns to In_Progress and keeps the original started_at', function () {
    $this->travelTo(Carbon::parse('2026-10-15 10:00', 'Asia/Jakarta'));
    $report = Report::factory()->onHold($this->pic)->create(['started_at' => now()->subDays(2)]);
    $startedAt = $report->started_at->toDateTimeString();

    $this->actingAs($this->pic)->post(route('pic.tasks.resume', $report))->assertRedirect(route('pic.tasks.show', $report));
    Notification::assertSentTo($report->user, ReportStatusChanged::class, fn ($n) => $n->report->status === ReportStatus::InProgress);

    $report->refresh();
    expect($report->status)->toBe(ReportStatus::InProgress)
        ->and($report->started_at->toDateTimeString())->toBe($startedAt)
        ->and($report->hold_reason)->toBeNull();
});

test('starting again after a hold cycle does not move started_at', function () {
    $this->travelTo(Carbon::parse('2026-10-15 10:00', 'Asia/Jakarta'));
    $report = Report::factory()->assigned($this->pic)->create();

    $this->actingAs($this->pic)->post(route('pic.tasks.start', $report));
    $first = $report->fresh()->started_at->toDateTimeString();

    $this->travelTo(Carbon::parse('2026-10-16 10:00', 'Asia/Jakarta'));
    $this->actingAs($this->pic)->post(route('pic.tasks.hold', $report), ['hold_reason' => 'Menunggu vendor datang.']);
    $this->actingAs($this->pic)->post(route('pic.tasks.resume', $report));

    expect($report->fresh()->started_at->toDateTimeString())->toBe($first);
});

test('actions in the wrong status are refused', function (string $state, string $route) {
    $report = Report::factory()->{$state}($this->pic)->create();

    $this->actingAs($this->pic)->post(route($route, $report), ['hold_reason' => 'Alasan yang cukup panjang.'])->assertForbidden();
})->with([
    ['inProgress', 'pic.tasks.start'],
    ['assigned', 'pic.tasks.hold'],
    ['assigned', 'pic.tasks.resume'],
    ['inProgress', 'pic.tasks.resume'],
    ['onHold', 'pic.tasks.hold'],
    ['closed', 'pic.tasks.start'],
]);

test('another pic can not act on the task', function () {
    $report = Report::factory()->assigned($this->pic)->create();

    $this->actingAs(User::factory()->pic()->create())->post(route('pic.tasks.start', $report))->assertForbidden();
    expect($report->fresh()->status)->toBe(ReportStatus::Assigned);
});

test('a pic can not reject a task: there is no such route', function () {
    $report = Report::factory()->assigned($this->pic)->create();

    $this->actingAs($this->pic)->post("/pic/tasks/{$report->id}/reject", ['rejection_reason' => 'Bukan tugas saya.'])->assertNotFound();
});

test('a stale start changes nothing', function () {
    $report = Report::factory()->assigned($this->pic)->create();
    Report::whereKey($report->id)->update(['status' => ReportStatus::Closed]); // row moved on, model stale

    expect(fn () => app(StartReport::class)->handle($report, $this->pic))
        ->toThrow(InvalidTransition::class);

    expect($report->fresh()->status)->toBe(ReportStatus::Closed)
        ->and($report->statusLogs()->count())->toBe(0);
    Notification::assertNothingSent();
});

test('a stale hold changes nothing', function () {
    $report = Report::factory()->inProgress($this->pic)->create();
    Report::whereKey($report->id)->update(['status' => ReportStatus::Closed]); // row moved on, model stale

    expect(fn () => app(HoldReport::class)->handle($report, $this->pic, 'Alasan yang cukup panjang.'))
        ->toThrow(InvalidTransition::class);

    expect($report->fresh()->status)->toBe(ReportStatus::Closed)
        ->and($report->statusLogs()->count())->toBe(0);
    Notification::assertNothingSent();
});

test('a stale resume changes nothing', function () {
    $report = Report::factory()->onHold($this->pic)->create();
    Report::whereKey($report->id)->update(['status' => ReportStatus::Closed]); // row moved on, model stale

    expect(fn () => app(ResumeReport::class)->handle($report, $this->pic))
        ->toThrow(InvalidTransition::class);

    expect($report->fresh()->status)->toBe(ReportStatus::Closed)
        ->and($report->statusLogs()->count())->toBe(0);
    Notification::assertNothingSent();
});

test('a pic who lost the task to a reassignment can not start it', function () {
    $report = Report::factory()->assigned($this->pic)->create();
    $other = User::factory()->pic()->create();
    Report::whereKey($report->id)->update(['assigned_pic_id' => $other->id]); // reassigned after the policy check, model stale

    expect(fn () => app(StartReport::class)->handle($report, $this->pic))
        ->toThrow(InvalidTransition::class);

    expect($report->fresh()->status)->toBe(ReportStatus::Assigned)
        ->and($report->statusLogs()->count())->toBe(0);
    Notification::assertNothingSent();
});

test('a pic who lost the task to a reassignment can not hold it', function () {
    $report = Report::factory()->inProgress($this->pic)->create();
    Report::whereKey($report->id)->update(['assigned_pic_id' => User::factory()->pic()->create()->id]); // model stale

    expect(fn () => app(HoldReport::class)->handle($report, $this->pic, 'Alasan yang cukup panjang.'))
        ->toThrow(InvalidTransition::class);

    expect($report->fresh()->status)->toBe(ReportStatus::InProgress)->and($report->statusLogs()->count())->toBe(0);
    Notification::assertNothingSent();
});

test('a pic who lost the task to a reassignment can not resume it', function () {
    $report = Report::factory()->onHold($this->pic)->create();
    Report::whereKey($report->id)->update(['assigned_pic_id' => User::factory()->pic()->create()->id]); // model stale

    expect(fn () => app(ResumeReport::class)->handle($report, $this->pic))
        ->toThrow(InvalidTransition::class);

    expect($report->fresh()->status)->toBe(ReportStatus::OnHold)->and($report->statusLogs()->count())->toBe(0);
    Notification::assertNothingSent();
});
