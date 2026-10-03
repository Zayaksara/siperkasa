<?php

namespace App\Actions\Reports;

use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\ReportStatusLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** The only place that changes a report's status. Call it inside a DB transaction. */
final class ReportTransitioner
{
    /**
     * @param  array<string, mixed>  $attributes  extra columns saved together with the new status
     *
     * @throws InvalidTransition
     */
    public function transition(Report $report, ReportStatus $to, ?User $actor, ?string $note = null, array $attributes = []): Report
    {
        return DB::transaction(function () use ($report, $to, $actor, $note, $attributes) {
            // Re-read current status under row lock to prevent stale-model race
            $currentStatus = Report::whereKey($report->id)->lockForUpdate()->value('status');
            // Status is already cast to enum by model
            $from = $currentStatus instanceof ReportStatus ? $currentStatus : ReportStatus::from($currentStatus);

            if (! $from->canTransitionTo($to)) {
                throw InvalidTransition::between($from, $to);
            }

            $report->forceFill([...$attributes, 'status' => $to])->save();

            $this->log($report, $from->value, $to->value, $actor, $note);

            return $report;
        });
    }

    /** Records an event that does not change the status (edit, reassign). */
    public function note(Report $report, ?User $actor, string $note): void
    {
        $this->log($report, $report->status->value, $report->status->value, $actor, $note);
    }

    public function logCreated(Report $report, ?User $actor): void
    {
        $this->log($report, null, $report->status->value, $actor, null);
    }

    private function log(Report $report, ?string $from, string $to, ?User $actor, ?string $note): void
    {
        ReportStatusLog::create([
            'report_id' => $report->id,
            'from_status' => $from,
            'to_status' => $to,
            'actor_id' => $actor?->id,
            'note' => $note,
        ]);
    }
}
