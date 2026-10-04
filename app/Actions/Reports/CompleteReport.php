<?php

namespace App\Actions\Reports;

use App\Enums\AttachmentType;
use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\User;
use App\Notifications\ReportStatusChanged;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;

final class CompleteReport
{
    public function __construct(
        private readonly ReportTransitioner $transitioner,
        private readonly AttachmentStore $attachments,
    ) {}

    /**
     * @param  array<string, mixed>  $data  repair_action, completed_on, repair_cost?, vendor_name?
     * @param  array<int, UploadedFile>  $photos
     *
     * @throws InvalidTransition when the report is no longer in progress
     */
    public function handle(Report $report, User $pic, array $data, array $photos): Report
    {
        $this->attachments->within($report, AttachmentType::Completion, $photos, [], function () use ($report, $pic, $data) {
            $this->transitioner->transition($report, ReportStatus::Closed, $pic, 'Perbaikan selesai', [
                'repair_action' => $data['repair_action'],
                'completed_on' => $data['completed_on'],
                'repair_cost' => $data['repair_cost'] ?? null,
                'vendor_name' => $data['vendor_name'] ?? null,
                'closed_at' => now(),
            ]);
        });

        Notification::send($report->user, new ReportStatusChanged($report));

        return $report;
    }
}
