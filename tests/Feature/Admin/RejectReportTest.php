<?php

use App\Actions\Reports\InvalidTransition;
use App\Actions\Reports\RejectReport;
use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\User;
use App\Notifications\ReportStatusChanged;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->admin = User::factory()->admin()->create();
    $this->report = Report::factory()->create();
});

function adminRejectPayload(array $overrides = []): array
{
    return array_merge([
        'rejection_reason' => 'Laporan ini duplikat dengan tiket yang sudah ada.',
    ], $overrides);
}

test('rejecting stores the reason, closes the report and tells the reporter', function () {
    $reason = 'Laporan ini duplikat dengan tiket yang sudah ada.';

    $this->actingAs($this->admin)->post(route('admin.reports.reject', $this->report), ['rejection_reason' => $reason])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.reports.show', $this->report));

    $report = $this->report->fresh();
    expect($report->status)->toBe(ReportStatus::Rejected)
        ->and($report->rejection_reason)->toBe($reason)
        ->and($report->admin_id)->toBe($this->admin->id);

    $log = $report->statusLogs()->sole();
    expect($log->to_status)->toBe('Rejected')->and($log->note)->toBe($reason);

    Notification::assertSentTo($report->user, ReportStatusChanged::class, fn ($n) => str_contains($n->toArray($report->user)['message'], $reason));
});

test('a reason of at least 10 characters is required', function () {
    $this->actingAs($this->admin)->post(route('admin.reports.reject', $this->report), [])
        ->assertSessionHasErrors('rejection_reason');

    $this->actingAs($this->admin)->post(route('admin.reports.reject', $this->report), ['rejection_reason' => 'pendek'])
        ->assertSessionHasErrors(['rejection_reason' => 'Alasan penolakan minimal 10 karakter.']);

    expect($this->report->fresh()->status)->toBe(ReportStatus::Pending);
});

test('a rejected report can not be rejected or validated again', function () {
    $this->actingAs($this->admin)->post(route('admin.reports.reject', $this->report), ['rejection_reason' => 'Alasan yang cukup panjang.']);

    $this->actingAs($this->admin)->post(route('admin.reports.reject', $this->report), ['rejection_reason' => 'Alasan kedua yang panjang.'])->assertForbidden();
    $this->actingAs($this->admin)->post(route('admin.reports.approve', $this->report), [
        'admin_priority' => 'High',
        'sla_date' => now()->addDay()->format('Y-m-d\TH:i'),
        'assigned_pic_id' => User::factory()->pic()->create()->id,
    ])->assertForbidden();

    expect($this->report->fresh()->rejection_reason)->toBe('Alasan yang cukup panjang.')
        ->and($this->report->fresh()->status)->toBe(ReportStatus::Rejected)
        ->and($this->report->statusLogs()->count())->toBe(1);
});

test('only admins can reject', function (string $state) {
    $this->actingAs(User::factory()->{$state}()->create())
        ->post(route('admin.reports.reject', $this->report), adminRejectPayload())->assertForbidden();
})->with(['reporter', 'pic']);

test('a stale reject changes nothing', function () {
    $report = Report::factory()->create(['status' => ReportStatus::Pending]);
    Report::whereKey($report->id)->update(['status' => ReportStatus::Closed]); // row moved on, model stale

    expect(fn () => app(RejectReport::class)->handle(
        $report,
        $this->admin,
        'Alasan yang cukup panjang.',
    ))->toThrow(InvalidTransition::class);

    expect($report->fresh()->status)->toBe(ReportStatus::Closed)
        ->and($report->statusLogs()->count())->toBe(0);
    Notification::assertNothingSent();
});
