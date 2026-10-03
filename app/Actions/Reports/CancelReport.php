<?php

namespace App\Actions\Reports;

use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class CancelReport
{
    public function __construct(private readonly ReportTransitioner $transitioner) {}

    /** @throws AuthorizationException when the report is no longer pending */
    public function handle(Report $report, User $actor): Report
    {
        return DB::transaction(function () use ($report, $actor) {
            $current = Report::whereKey($report->id)->lockForUpdate()->value('status');

            if ($current !== ReportStatus::Pending) {
                throw new AuthorizationException;
            }

            return $this->transitioner->transition(
                $report,
                ReportStatus::Cancelled,
                $actor,
                'Dibatalkan oleh pelapor',
                ['cancelled_at' => now()],
            );
        });
    }
}
