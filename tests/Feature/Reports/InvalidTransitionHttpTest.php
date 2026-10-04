<?php

use App\Actions\Reports\InvalidTransition;
use App\Actions\Reports\ReportTransitioner;
use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware('web')->post('/_test/stale-transition/{report}', function (Report $report) {
        // Stale page: the model shows Pending, the database row has moved on.
        Report::whereKey($report->id)->update(['status' => ReportStatus::Cancelled]);

        return app(ReportTransitioner::class)
            ->transition($report, ReportStatus::Assigned, auth()->user());
    })->name('test.stale');
    app('router')->getRoutes()->refreshNameLookups();
});

test('a stale transition redirects back with an error toast and changes nothing', function () {
    $admin = User::factory()->admin()->create();
    $report = Report::factory()->create();

    $this->actingAs($admin)->from('/dashboard')->post(route('test.stale', $report))
        ->assertRedirect('/dashboard')
        ->assertInertiaFlash('toast.message', 'Status laporan sudah berubah. Muat ulang halaman.')
        ->assertInertiaFlash('toast.type', 'error');

    expect($report->fresh()->status)->toBe(ReportStatus::Cancelled)
        ->and($report->statusLogs()->count())->toBe(0);
});

test('a stale transition answers 409 for JSON requests', function () {
    $admin = User::factory()->admin()->create();
    $report = Report::factory()->create();

    $this->actingAs($admin)->postJson(route('test.stale', $report))
        ->assertStatus(409)
        ->assertJsonPath('message', 'Status laporan sudah berubah menjadi Dibatalkan. Muat ulang halaman.');
});

test('a stale transition to the same status names the current status instead of "from X to X"', function () {
    $message = InvalidTransition::changed(ReportStatus::Closed)->getMessage();

    expect($message)->toBe('Status laporan sudah berubah menjadi Selesai. Muat ulang halaman.');
});

test('a PIC who lost the task is sent to the task list with the reason', function () {
    $pic = User::factory()->pic()->create();
    $report = Report::factory()->assigned($pic)->create();
    Route::middleware('web')->post('/_test/lost/{report}', function (Report $report) use ($pic) {
        Report::whereKey($report->id)->update(['assigned_pic_id' => User::factory()->pic()->create()->id]);

        return app(ReportTransitioner::class)->transition($report, ReportStatus::InProgress, $pic, actorMustBe: $pic->id);
    })->name('test.lost');
    app('router')->getRoutes()->refreshNameLookups();

    $this->actingAs($pic)->from(route('pic.tasks.show', $report))->post(route('test.lost', $report))
        ->assertRedirect(route('pic.tasks.index'))
        ->assertInertiaFlash('toast.message', 'Laporan tidak lagi ditugaskan kepada Anda.')
        ->assertInertiaFlash('toast.type', 'error');
});
