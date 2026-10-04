<?php

namespace App\Actions\Reports;

use App\Enums\Priority;
use App\Enums\ReportStatus;
use App\Models\Category;
use App\Models\Report;
use App\Models\User;
use App\Notifications\ReportAssigned;
use App\Notifications\ReportStatusChanged;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Notification;

final class ValidateReport
{
    public function __construct(private readonly ReportTransitioner $transitioner) {}

    /**
     * Assigns the report. The admin may correct the title and category in the same step: a real change is
     * saved together with the status change (same lock) and appended to the timeline note.
     */
    public function handle(Report $report, User $admin, Priority $priority, CarbonInterface $sla, User $pic, ?string $title = null, ?int $categoryId = null): Report
    {
        $report = $this->transitioner->guard($report, ReportStatus::cases(), function () use ($report, $admin, $priority, $sla, $pic, $title, $categoryId) {
            // Re-read under the lock so the "from" values in the note are the real ones.
            $report->refresh()->loadMissing('category');

            $note = sprintf(
                'Ditugaskan ke %s · Prioritas %s · SLA %s',
                $pic->name,
                $priority->label(),
                $sla->locale('id')->translatedFormat('d M Y H:i'),
            );
            $attributes = [
                'admin_id' => $admin->id,
                'admin_priority' => $priority,
                'sla_date' => $sla,
                'assigned_pic_id' => $pic->id,
                'validated_at' => now(),
            ];

            if ($title !== null && $title !== $report->title) {
                $note .= sprintf(' · Judul diubah: "%s" → "%s"', $report->title, $title);
                $attributes['title'] = $title;
            }

            if ($categoryId !== null && $categoryId !== $report->category_id) {
                $note .= sprintf(' · Kategori diubah: %s → %s', $report->category->name, Category::findOrFail($categoryId)->name);
                $attributes['category_id'] = $categoryId;
            }

            return $this->transitioner->transition($report, ReportStatus::Assigned, $admin, $note, $attributes);
        });

        Notification::send($pic, new ReportAssigned($report));
        Notification::send($report->user, new ReportStatusChanged($report));

        return $report;
    }
}
