<?php

namespace App\Actions\Reports;

use App\Enums\AttachmentType;
use App\Enums\ReportStatus;
use App\Enums\UserRole;
use App\Models\Report;
use App\Models\User;
use App\Notifications\ReportSubmitted;
use App\Support\TicketNumber;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class SubmitReport
{
    private const TICKET_ATTEMPTS = 3;

    public function __construct(
        private readonly ReportTransitioner $transitioner,
        private readonly AttachmentStore $attachments,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, UploadedFile>  $photos
     */
    public function handle(User $user, array $data, array $photos): Report
    {
        $report = $this->createReport($user, $data);

        // Photos are stored only after the report row is committed (so a retried
        // ticket attempt never leaves files behind); clean up if storing fails.
        try {
            $this->attachments->within($report, AttachmentType::Damage, $photos, [], fn () => null);
        } catch (Throwable $e) {
            Storage::disk('local')->deleteDirectory("reports/{$report->id}");
            $report->delete();

            throw $e;
        }

        Notification::send(
            User::query()->where('role', UserRole::Admin)->where('is_active', true)->get(),
            new ReportSubmitted($report),
        );

        return $report;
    }

    /** @param  array<string, mixed>  $data */
    private function createReport(User $user, array $data): Report
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                return DB::transaction(function () use ($user, $data) {
                    $report = Report::create([
                        ...$data,
                        'user_id' => $user->id,
                        'ticket_number' => TicketNumber::next(),
                        'status' => ReportStatus::Pending,
                    ]);

                    $this->transitioner->logCreated($report, $user);

                    return $report;
                }, 3);
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt >= self::TICKET_ATTEMPTS || ! str_contains($e->getMessage(), 'ticket_number')) {
                    throw $e;
                }
            }
        }
    }
}
