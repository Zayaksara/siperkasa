<?php

namespace App\Notifications;

use App\Models\Report;
use App\Support\ReportUrl;
use Illuminate\Notifications\Notification;

class ReportSubmitted extends Notification
{
    public function __construct(public Report $report) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'report_id' => $this->report->id,
            'ticket_number' => $this->report->ticket_number,
            'status' => $this->report->status->value,
            'message' => "Laporan baru {$this->report->ticket_number}: {$this->report->title}.",
            'url' => ReportUrl::for($this->report, $notifiable),
        ];
    }
}
