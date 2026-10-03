<?php

namespace App\Actions\Reports;

use App\Enums\Priority;
use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\User;
use App\Notifications\ReportAssigned;
use App\Notifications\ReportStatusChanged;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

final class ValidateReport
{
    public function __construct(private readonly ReportTransitioner $transitioner) {}

    public function handle(Report $report, User $admin, Priority $priority, CarbonInterface $sla, User $pic): Report
    {
        $report = DB::transaction(fn () => $this->transitioner->transition(
            $report,
            ReportStatus::Assigned,
            $admin,
            sprintf(
                'Ditugaskan ke %s · Prioritas %s · SLA %s',
                $pic->name,
                $priority->label(),
                $sla->locale('id')->translatedFormat('d M Y H:i'),
            ),
            [
                'admin_id' => $admin->id,
                'admin_priority' => $priority,
                'sla_date' => $sla,
                'assigned_pic_id' => $pic->id,
                'validated_at' => now(),
            ],
        ));

        Notification::send($pic, new ReportAssigned($report));
        Notification::send($report->user, new ReportStatusChanged($report));

        return $report;
    }
}
