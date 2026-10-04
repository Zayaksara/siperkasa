<?php

use App\Enums\ReportStatus;
use App\Models\Category;
use App\Models\Report;
use App\Models\Room;
use App\Models\User;
use Database\Seeders\DemoReportSeeder;

test('the demo seeder fills every status with logs and valid tickets', function () {
    User::factory()->admin()->create();
    User::factory()->pic()->count(2)->create();
    User::factory()->count(3)->create();
    Category::factory()->count(3)->create();
    Room::factory()->count(3)->create();

    $this->seed(DemoReportSeeder::class);

    expect(Report::count())->toBe(30);

    foreach (ReportStatus::cases() as $status) {
        expect(Report::where('status', $status)->count())->toBeGreaterThan(0, "no demo report with status {$status->value}");
    }

    expect(Report::pluck('ticket_number')->unique()->count())->toBe(30);
    expect(Report::pluck('ticket_number')->every(fn ($t) => preg_match('/^SPK-\d{4}-\d{4}$/', $t) === 1))->toBeTrue();

    Report::with('statusLogs')->get()->each(function (Report $report) {
        expect($report->statusLogs)->not->toBeEmpty();
        expect($report->statusLogs->last()->to_status)->toBe($report->status->value);
    });
});

test('closed demo reports are completed after they were created and not in the future', function () {
    User::factory()->admin()->create();
    User::factory()->pic()->create();
    User::factory()->create();
    Category::factory()->create();
    Room::factory()->create();

    $this->seed(DemoReportSeeder::class);

    Report::where('status', ReportStatus::Closed)->get()->each(function (Report $report) {
        expect($report->closed_at->gte($report->created_at))->toBeTrue()
            ->and($report->closed_at->lte(now()))->toBeTrue()
            ->and($report->completed_on)->not->toBeNull();
    });
});

test('the seeder does nothing when the base data is missing', function () {
    $this->seed(DemoReportSeeder::class);

    expect(Report::count())->toBe(0);
});

test('seeding twice gives 30 reports (idempotent)', function () {
    User::factory()->admin()->create();
    User::factory()->pic()->count(2)->create();
    User::factory()->count(3)->create();
    Category::factory()->count(3)->create();
    Room::factory()->count(3)->create();

    $this->seed(DemoReportSeeder::class);
    expect(Report::count())->toBe(30);

    $this->seed(DemoReportSeeder::class);
    expect(Report::count())->toBe(30, 'seeding twice should not add more reports');
});

test('demo report timestamps are ordered and never in the future', function () {
    User::factory()->admin()->create();
    User::factory()->pic()->count(2)->create();
    User::factory()->count(3)->create();
    Category::factory()->count(3)->create();
    Room::factory()->count(3)->create();

    $this->seed(DemoReportSeeder::class);

    Report::with('statusLogs')->get()->each(function (Report $report) {
        // No timestamp should be in the future
        expect($report->created_at->lte(now()))->toBeTrue();
        expect($report->validated_at?->lte(now()) ?? true)->toBeTrue();
        expect($report->started_at?->lte(now()) ?? true)->toBeTrue();
        expect($report->closed_at?->lte(now()) ?? true)->toBeTrue();
        expect($report->cancelled_at?->lte(now()) ?? true)->toBeTrue();

        // Maintain order: created_at ≤ validated_at ≤ started_at ≤ closed_at
        if ($report->validated_at) {
            expect($report->validated_at->gte($report->created_at))->toBeTrue('validated_at must be ≥ created_at');
        }
        if ($report->started_at) {
            expect($report->started_at->gte($report->validated_at ?? $report->created_at))->toBeTrue('started_at must be ≥ validated_at');
        }
        if ($report->closed_at) {
            expect($report->closed_at->gte($report->started_at ?? $report->validated_at ?? $report->created_at))->toBeTrue('closed_at must be ≥ started_at');
        }

        // Status logs should follow the same ordering
        $report->statusLogs->each(function ($log) use ($report) {
            expect($log->created_at->lte(now()))->toBeTrue('log timestamp must not be in future');
            expect($log->created_at->gte($report->created_at))->toBeTrue('log must be ≥ created_at');
        });
    });
});
