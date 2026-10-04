<?php

namespace App\Actions\Reports;

use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\ReportStatusLog;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;

/** The only place that changes a report's status. transition() opens its own DB transaction. */
final class ReportTransitioner
{
    /**
     * @param  array<string, mixed>  $attributes  extra columns saved together with the new status
     * @param  int|null  $actorMustBe  when set, the assigned PIC (re-read under the lock) must be this user id
     *
     * @throws InvalidTransition
     */
    public function transition(Report $report, ReportStatus $to, ?User $actor, ?string $note = null, array $attributes = [], ?int $actorMustBe = null): Report
    {
        return DB::transaction(function () use ($report, $to, $actor, $note, $attributes, $actorMustBe) {
            $from = $this->lockStatus($report, $to, $actorMustBe);

            if (! $from->canTransitionTo($to)) {
                throw InvalidTransition::changed($from);
            }

            $report->forceFill([...$attributes, 'status' => $to])->save();

            $this->log($report, $from->value, $to->value, $actor, $note);

            return $report;
        });
    }

    /**
     * Runs $work in a transaction while the report row is locked and its status (re-read under the lock) is one of $allowed.
     *
     * @param  array<int, ReportStatus>  $allowed
     * @param  int|null  $actorMustBe  when set, the assigned PIC (re-read under the lock) must be this user id
     *
     * @throws InvalidTransition when the status is not allowed
     */
    public function guard(Report $report, array $allowed, Closure $work, ?int $actorMustBe = null): mixed
    {
        return DB::transaction(function () use ($report, $allowed, $work, $actorMustBe) {
            $status = $this->lockStatus($report, null, $actorMustBe);

            if (! in_array($status, $allowed, true)) {
                throw InvalidTransition::changed($status);
            }

            return $work();
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
    private function lockStatus(Report $report, ?ReportStatus $to = null, ?int $actorMustBe = null): ReportStatus
    {
        $row = Report::whereKey($report->id)->lockForUpdate()->first(['status', 'assigned_pic_id']);

        if ($row === null) {
            throw InvalidTransition::changed($report->status);
        }

        if ($actorMustBe !== null && $row->assigned_pic_id !== $actorMustBe) {
            throw InvalidTransition::notAssigned();
        }

        $status = $row->status;

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
