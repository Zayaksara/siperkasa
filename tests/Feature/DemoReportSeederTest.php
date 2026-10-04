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
