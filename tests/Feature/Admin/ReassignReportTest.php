<?php

use App\Actions\Reports\InvalidTransition;
use App\Actions\Reports\ReassignReport;
use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\User;
use App\Notifications\ReportAssigned;
use App\Notifications\ReportStatusChanged;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->admin = User::factory()->admin()->create();
    $this->oldPic = User::factory()->pic()->create(['name' => 'Pak Lama']);
    $this->newPic = User::factory()->pic()->create(['name' => 'Bu Baru']);
});

test('reassigning keeps the status, logs the change and notifies the new pic and the reporter', function (string $state, ReportStatus $status) {
    $report = Report::factory()->{$state}($this->oldPic)->create();

    $this->actingAs($this->admin)->post(route('admin.reports.reassign', $report), ['assigned_pic_id' => $this->newPic->id])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.reports.show', $report));

    $report->refresh();
    expect($report->assigned_pic_id)->toBe($this->newPic->id)
        ->and($report->status)->toBe($status);

    $log = $report->statusLogs()->get()->last();
    expect($log->from_status)->toBe($status->value)
        ->and($log->to_status)->toBe($status->value)
        ->and($log->note)->toBe('PIC: Pak Lama → Bu Baru');

    Notification::assertSentTo($this->newPic, ReportAssigned::class);
    Notification::assertSentTo($report->user, ReportStatusChanged::class);
})->with([
    ['assigned', ReportStatus::Assigned],
    ['inProgress', ReportStatus::InProgress],
    ['onHold', ReportStatus::OnHold],
]);

test('the new pic must differ from the current one and be an active, non-deleted pic', function () {
    $report = Report::factory()->inProgress($this->oldPic)->create();
    $inactive = User::factory()->pic()->inactive()->create();
    $trashed = User::factory()->pic()->create();
    $trashed->delete();

    $this->actingAs($this->admin)->post(route('admin.reports.reassign', $report), ['assigned_pic_id' => $this->oldPic->id])
        ->assertSessionHasErrors(['assigned_pic_id' => 'PIC baru harus berbeda dari PIC saat ini.']);

    foreach ([$inactive, $trashed] as $pic) {
        $this->actingAs($this->admin)->post(route('admin.reports.reassign', $report), ['assigned_pic_id' => $pic->id])
            ->assertSessionHasErrors('assigned_pic_id');
    }

    expect($report->fresh()->assigned_pic_id)->toBe($this->oldPic->id);
    Notification::assertNothingSent();
});

test('a task can be moved away from a pic who has since been deactivated', function () {
    $report = Report::factory()->inProgress($this->oldPic)->create();
    $this->oldPic->update(['is_active' => false]);

    $this->actingAs($this->admin)->post(route('admin.reports.reassign', $report), ['assigned_pic_id' => $this->newPic->id])
        ->assertSessionHasNoErrors();

    expect($report->fresh()->assigned_pic_id)->toBe($this->newPic->id);
});

test('finished or unassigned reports can not be reassigned', function (string $state) {
    $report = Report::factory()->{$state}()->create();

    $this->actingAs($this->admin)->post(route('admin.reports.reassign', $report), ['assigned_pic_id' => $this->newPic->id])->assertForbidden();
})->with(['pending', 'closed', 'rejected', 'cancelled']);

test('only admins can reassign', function (string $state) {
    $report = Report::factory()->assigned($this->oldPic)->create();

    $this->actingAs(User::factory()->{$state}()->create())->post(route('admin.reports.reassign', $report), ['assigned_pic_id' => $this->newPic->id])->assertForbidden();
})->with(['reporter', 'pic']);

test('a stale reassign changes nothing', function (ReportStatus $final) {
    $report = Report::factory()->inProgress($this->oldPic)->create();
    Report::whereKey($report->id)->update(['status' => $final]); // row moved on, model stale

    expect(fn () => app(ReassignReport::class)->handle($report, $this->admin, $this->newPic))
        ->toThrow(InvalidTransition::class);

    expect($report->fresh()->assigned_pic_id)->toBe($this->oldPic->id)
        ->and($report->statusLogs()->count())->toBe(0);
    Notification::assertNothingSent();
})->with([ReportStatus::Closed, ReportStatus::Rejected]);
