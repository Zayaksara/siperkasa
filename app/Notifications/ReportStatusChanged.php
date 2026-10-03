<?php

namespace App\Notifications;

use App\Enums\ReportStatus;
use App\Models\Report;
use App\Support\ReportUrl;
use Illuminate\Notifications\Notification;

class ReportStatusChanged extends Notification
{
    public function __construct(public Report $report, public ?string $message = null) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $ticket = $this->report->ticket_number;

        return [
            'report_id' => $this->report->id,
            'ticket_number' => $ticket,
            'status' => $this->report->status->value,
            'message' => $this->message ?? match ($this->report->status) {
                ReportStatus::Assigned => "Laporan {$ticket} sudah divalidasi dan ditugaskan ke PIC.",
                ReportStatus::InProgress => "Laporan {$ticket} sedang dikerjakan.",
                ReportStatus::OnHold => "Laporan {$ticket} ditunda sementara.",
                ReportStatus::Closed => "Laporan {$ticket} sudah selesai.",
                ReportStatus::Rejected => "Laporan {$ticket} ditolak.",
                default => "Status laporan {$ticket}: {$this->report->status->label()}.",
            },
            'url' => ReportUrl::for($this->report, $notifiable),
        ];
    }
}
