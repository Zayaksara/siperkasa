<?php

use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\User;
use App\Notifications\ReportAssigned;
use App\Notifications\ReportStatusChanged;
use App\Notifications\ReportSubmitted;

test('all notifications use only the database channel', function () {
    $report = Report::factory()->create();
    $user = User::factory()->create();

    foreach ([new ReportSubmitted($report), new ReportAssigned($report), new ReportStatusChanged($report)] as $notification) {
        expect($notification->via($user))->toBe(['database']);
    }
});

test('submitted notification carries the standard payload', function () {
    $report = Report::factory()->create(['title' => 'Proyektor mati']);
    $admin = User::factory()->admin()->create();

    $payload = (new ReportSubmitted($report))->toArray($admin);

    expect($payload)->toHaveKeys(['report_id', 'ticket_number', 'status', 'message', 'url'])
        ->and($payload['report_id'])->toBe($report->id)
        ->and($payload['ticket_number'])->toBe($report->ticket_number)
        ->and($payload['status'])->toBe('Pending')
        ->and($payload['message'])->toBe("Laporan baru {$report->ticket_number}: Proyektor mati.");
});

test('assigned notification tells the pic about the task', function () {
    $report = Report::factory()->assigned()->create(['title' => 'AC bocor']);

    $payload = (new ReportAssigned($report))->toArray($report->assignedPic);

    expect($payload['message'])->toBe("Anda ditugaskan menangani laporan {$report->ticket_number}: AC bocor.")
        ->and($payload['status'])->toBe('Assigned');
});

test('status changed notification has a message per status', function (ReportStatus $status, string $expected) {
    $report = Report::factory()->create();
    $report->status = $status;

    $payload = (new ReportStatusChanged($report))->toArray($report->user);

    expect($payload['message'])->toBe(str_replace('{t}', $report->ticket_number, $expected));
})->with([
    [ReportStatus::Assigned, 'Laporan {t} sudah divalidasi dan ditugaskan ke PIC.'],
    [ReportStatus::InProgress, 'Laporan {t} sedang dikerjakan.'],
    [ReportStatus::OnHold, 'Laporan {t} ditunda sementara.'],
    [ReportStatus::Closed, 'Laporan {t} sudah selesai.'],
    [ReportStatus::Rejected, 'Laporan {t} ditolak.'],
]);

test('a custom message overrides the default', function () {
    $report = Report::factory()->create();

    $payload = (new ReportStatusChanged($report, 'Pesan khusus'))->toArray($report->user);

    expect($payload['message'])->toBe('Pesan khusus');
});

test('the url is a safe fallback when role routes do not exist yet', function () {
    $report = Report::factory()->create();
    $admin = User::factory()->admin()->create();

    expect((new ReportSubmitted($report))->toArray($admin)['url'])->toBeString()->not->toBeEmpty();
});

test('the url is a relative path, independent of host', function () {
    $report = Report::factory()->create();
    $admin = User::factory()->admin()->create();

    $url = (new ReportSubmitted($report))->toArray($admin)['url'];

    expect($url)->toBe(route('admin.reports.show', $report, false))->toStartWith('/')->not->toStartWith('//');
});
