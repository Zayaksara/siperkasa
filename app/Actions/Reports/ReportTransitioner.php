<?php

namespace App\Actions\Reports;

use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\ReportStatusLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** The only place that changes a report's status. transition() opens its own DB transaction. */
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
            $from = $this->lockStatus($report, $to);

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
        DB::transaction(function () use ($report, $actor, $note) {
            $status = $this->lockStatus($report)->value;

            $this->log($report, $status, $status, $actor, $note);
        });
    }

    public function logCreated(Report $report, ?User $actor): void
    {
        $this->log($report, null, $report->status->value, $actor, null);
    }

    /** Re-reads the status under a row lock and syncs the model to it, so a stale model cannot hide a change. */
    private function lockStatus(Report $report, ?ReportStatus $to = null): ReportStatus
    {
        $status = Report::whereKey($report->id)->lockForUpdate()->value('status');

        if ($status === null) {
            throw InvalidTransition::between($report->status, $to ?? $report->status);
        }

        $report->setAttribute('status', $status);
        $report->syncOriginalAttribute('status');

        return $status;
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
