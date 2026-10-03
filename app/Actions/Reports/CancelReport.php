<?php

namespace App\Actions\Reports;

use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class CancelReport
{
    public function __construct(private readonly ReportTransitioner $transitioner) {}

    public function handle(Report $report, User $actor): Report
    {
        return DB::transaction(fn () => $this->transitioner->transition(
            $report,
            ReportStatus::Cancelled,
            $actor,
            'Dibatalkan oleh pelapor',
            ['cancelled_at' => now()],
        ));
    }
}
