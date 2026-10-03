<?php

namespace App\Actions\Reports;

use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\User;
use App\Notifications\ReportAssigned;
use App\Notifications\ReportStatusChanged;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

final class ReassignReport
{
    public function __construct(private readonly ReportTransitioner $transitioner) {}

    /**
     * @throws InvalidTransition when the report is no longer assigned, in progress or on hold
     * @throws ValidationException when the new PIC is the current PIC
     */
    public function handle(Report $report, User $admin, User $newPic): Report
    {
        $this->transitioner->guard(
            $report,
            [ReportStatus::Assigned, ReportStatus::InProgress, ReportStatus::OnHold],
            function () use ($report, $admin, $newPic) {
                $currentId = Report::whereKey($report->id)->value('assigned_pic_id');

                if ($currentId === $newPic->id) {
                    throw ValidationException::withMessages(['assigned_pic_id' => 'PIC baru harus berbeda dari PIC saat ini.']);
                }

                $oldName = $currentId === null ? '-' : (User::withTrashed()->whereKey($currentId)->value('name') ?? '-');

                $report->forceFill(['assigned_pic_id' => $newPic->id])->save();
                $report->unsetRelation('assignedPic');

                $this->transitioner->note($report, $admin, "PIC: {$oldName} → {$newPic->name}");
            },
        );

        Notification::send($newPic, new ReportAssigned($report));
        Notification::send($report->user, new ReportStatusChanged($report, "Laporan {$report->ticket_number} dialihkan ke PIC {$newPic->name}."));

        return $report;
    }
}
