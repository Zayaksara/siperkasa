<?php

namespace App\Actions\Reports;

use App\Enums\AttachmentType;
use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

final class UpdateReport
{
    public function __construct(
        private readonly ReportTransitioner $transitioner,
        private readonly AttachmentStore $attachments,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, UploadedFile>  $newPhotos
     * @param  array<int, int|string>  $removeIds
     *
     * @throws AuthorizationException when the report is no longer pending
     */
    public function handle(Report $report, User $actor, array $data, array $newPhotos, array $removeIds): Report
    {
        return DB::transaction(function () use ($report, $actor, $data, $newPhotos, $removeIds) {
            $current = Report::whereKey($report->id)->lockForUpdate()->value('status');

            if ($current !== ReportStatus::Pending) {
                throw new AuthorizationException;
            }

            $report->update($data);

            $this->attachments->remove($report, AttachmentType::Damage, $removeIds);
            $this->attachments->add($report, AttachmentType::Damage, $newPhotos);

            $this->transitioner->note($report, $actor, 'Laporan diubah');

            return $report;
        });
    }
}
