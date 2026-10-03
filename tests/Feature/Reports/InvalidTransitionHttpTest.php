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
        ->assertJsonPath('message', InvalidTransition::between(ReportStatus::Cancelled, ReportStatus::Assigned)->getMessage());
});
