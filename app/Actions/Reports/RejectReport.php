<?php

namespace App\Actions\Reports;

use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\User;
use App\Notifications\ReportStatusChanged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

final class RejectReport
{
    public function __construct(private readonly ReportTransitioner $transitioner) {}

    public function handle(Report $report, User $admin, string $reason): Report
    {
        $report = DB::transaction(fn () => $this->transitioner->transition(
            $report,
            ReportStatus::Rejected,
            $admin,
            $reason,
            ['admin_id' => $admin->id, 'rejection_reason' => $reason],
        ));

        Notification::send($report->user, new ReportStatusChanged($report, "Laporan {$report->ticket_number} ditolak: {$reason}"));

        return $report;
    }
}
