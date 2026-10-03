<?php

use App\Actions\Reports\ReportTransitioner;
use App\Models\Report;
use App\Models\User;
use App\Support\ReportPresenter;

test('the owner sees the detail with timeline and permissions', function () {
    $owner = User::factory()->create();
    $report = Report::factory()->for($owner)->create();
    app(ReportTransitioner::class)->logCreated($report, $owner);

    $this->actingAs($owner)->get(route('reports.show', $report))
        ->assertInertia(fn ($page) => $page
            ->component('reports/Show')
            ->where('report.id', $report->id)
            ->has('report.timeline', 1)
            ->where('can.update', true)
            ->where('can.cancel', true));
});

test('another reporter gets 403', function () {
    $report = Report::factory()->create();

    $this->actingAs(User::factory()->create())->get(route('reports.show', $report))->assertForbidden();
});

test('a closed report shows the completion data to its owner and offers no edit', function () {
    $owner = User::factory()->create();
    $report = Report::factory()->for($owner)->closed()->create();

    $this->actingAs($owner)->get(route('reports.show', $report))
        ->assertInertia(fn ($page) => $page
            ->where('report.status', 'Closed')
            ->where('report.repair_action', 'Ganti lampu proyektor.')
            ->where('report.vendor_name', 'CV Teknik Jaya')
            ->where('can.update', false)
            ->where('can.cancel', false));
});

test('a rejected report shows the reason', function () {
    $owner = User::factory()->create();
    $report = Report::factory()->for($owner)->rejected()->create();

    $this->actingAs($owner)->get(route('reports.show', $report))
        ->assertInertia(fn ($page) => $page->where('report.rejection_reason', 'Laporan duplikat dengan tiket lain.'));
});

test('a report of a soft-deleted reporter still presents the owner name', function () {
    $owner = User::factory()->create(['name' => 'Mahasiswa Lama']);
    $report = Report::factory()->for($owner)->create();
    $owner->delete();

    $summary = ReportPresenter::summary(Report::with('user')->findOrFail($report->id));
    $detail = ReportPresenter::detail(Report::findOrFail($report->id));

    expect($summary['reporter'])->toBe('Mahasiswa Lama')
        ->and($detail['reporter'])->toBe('Mahasiswa Lama');
});
