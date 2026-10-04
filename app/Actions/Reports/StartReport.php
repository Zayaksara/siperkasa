<?php

namespace App\Actions\Reports;

use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\User;
use App\Notifications\ReportStatusChanged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

final class StartReport
{
    public function __construct(private readonly ReportTransitioner $transitioner) {}

    public function handle(Report $report, User $pic): Report
    {
        $report = DB::transaction(fn () => $this->transitioner->transition(
            $report,
            ReportStatus::InProgress,
            $pic,
            'Pengerjaan dimulai',
            ['started_at' => $report->started_at ?? now()],
            $pic->id,
        ));

        Notification::send($report->user, new ReportStatusChanged($report));

        return $report;
    }
}
