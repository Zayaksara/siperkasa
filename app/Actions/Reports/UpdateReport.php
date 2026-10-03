<?php

namespace App\Actions\Reports;

use App\Enums\AttachmentType;
use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

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
     * @throws ValidationException when the final photo count is outside the allowed range
     */
    public function handle(Report $report, User $actor, array $data, array $newPhotos, array $removeIds): Report
    {
        // Files are written outside the transaction, rows inside it. Old files are
        // deleted only after commit; new files are deleted again if the transaction fails.
        $newPaths = $this->attachments->putFiles($report, AttachmentType::Damage, $newPhotos);

        try {
            $oldPaths = DB::transaction(function () use ($report, $actor, $data, $newPaths, $removeIds) {
                $current = Report::whereKey($report->id)->lockForUpdate()->value('status');

                if ($current !== ReportStatus::Pending) {
                    throw new AuthorizationException;
                }

                $this->assertPhotoCount($report, $removeIds, count($newPaths));

                $report->update($data);

                $removed = $this->attachments->remove($report, AttachmentType::Damage, $removeIds);
                $this->attachments->addRows($report, AttachmentType::Damage, $newPaths);

                $this->transitioner->note($report, $actor, 'Laporan diubah');

                return $removed;
            });
        } catch (Throwable $e) {
            $this->attachments->deleteFiles($newPaths);

            throw $e;
        }

        $this->attachments->deleteFiles($oldPaths);

        return $report;
    }

    /** @param  array<int, int|string>  $removeIds */
    private function assertPhotoCount(Report $report, array $removeIds, int $added): void
    {
        $kept = $report->damagePhotos()->whereNotIn('id', array_map('intval', $removeIds))->count();
        $total = $kept + $added;

        $message = match (true) {
            $total < config('siperkasa.photos.min') => 'Minimal 1 foto kerusakan.',
            $total > config('siperkasa.photos.max') => 'Maksimal 3 foto.',
            default => null,
        };

        if ($message !== null) {
            throw ValidationException::withMessages(['photos' => $message]);
        }
    }
}
