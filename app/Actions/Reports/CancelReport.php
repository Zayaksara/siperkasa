<?php

namespace App\Actions\Reports;

use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\User;

final class CancelReport
{
    public function __construct(private readonly ReportTransitioner $transitioner) {}

    /** @throws InvalidTransition when the report is no longer pending */
    public function handle(Report $report, User $actor): Report
    {
        return $this->transitioner->transition(
            $report,
            ReportStatus::Cancelled,
            $actor,
            'Dibatalkan oleh pelapor',
            ['cancelled_at' => now()],
        );
    }
}
