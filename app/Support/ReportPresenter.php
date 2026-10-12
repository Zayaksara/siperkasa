<?php

namespace App\Support;

use App\Enums\AttachmentType;
use App\Enums\ReportStatus;
use App\Models\Report;

/** Turns a Report into the arrays the Inertia pages consume. */
final class ReportPresenter
{
    /** @return array<string, mixed> */
    public static function summary(Report $report): array
    {
        return [
            'id' => $report->id,
            'ticket_number' => $report->ticket_number,
            'title' => $report->title,
            'status' => $report->status->value,
            'status_label' => $report->status->label(),
            'user_priority' => $report->user_priority->value,
            'user_priority_label' => $report->user_priority->label(),
            'admin_priority' => $report->admin_priority?->value,
            'admin_priority_label' => $report->admin_priority?->label(),
            'is_overdue' => $report->isOverdue(),
            'sla_date' => $report->sla_date?->toIso8601String(),
            'created_at' => $report->created_at?->toIso8601String(),
            'category' => $report->category?->name,
            'room' => $report->room?->name,
            'building' => $report->room?->building?->name,
            'reporter' => $report->user?->name,
            'pic' => $report->assignedPic?->name,
        ];
    }

    /**
     * The reporter's phone is admin-only; PIC and owner payloads never carry the key.
     *
     * @return array<string, mixed>
     */
    public static function detail(Report $report, bool $withPhone = false): array
    {
        $report->loadMissing(['category', 'room.building', 'asset.category', 'user', 'admin', 'assignedPic', 'attachments', 'statusLogs.actor']);

        return [
            ...self::summary($report),
            'description' => $report->description,
            ...($withPhone ? ['reporter_phone' => $report->user?->phone] : []),
            'reporter_nim_nip' => $report->user?->nim_nip,
            'reporter_faculty' => $report->user?->faculty,
            'reporter_study_program' => $report->user?->study_program,
            'category_id' => $report->category_id,
            'room_id' => $report->room_id,
            'asset' => $report->asset ? [
                'id' => $report->asset->id,
                'asset_code' => $report->asset->asset_code,
                'name' => $report->asset->name,
                'brand' => $report->asset->brand,
                'category' => $report->asset->category?->name,
            ] : null,
            'admin' => $report->admin?->name,
            'rejection_reason' => $report->rejection_reason,
            'hold_reason' => $report->hold_reason,
            'repair_action' => $report->repair_action,
            'completed_on' => $report->completed_on?->toDateString(),
            'repair_cost' => $report->repair_cost,
            'vendor_name' => $report->vendor_name,
            'validated_at' => $report->validated_at?->toIso8601String(),
            'started_at' => $report->started_at?->toIso8601String(),
            'closed_at' => $report->closed_at?->toIso8601String(),
            'cancelled_at' => $report->cancelled_at?->toIso8601String(),
            'damage_photos' => self::photos($report, AttachmentType::Damage),
            'completion_photos' => self::photos($report, AttachmentType::Completion),
            'timeline' => $report->statusLogs->map(fn ($log) => [
                'id' => $log->id,
                'from_status' => $log->from_status,
                'to_status' => $log->to_status,
                'to_label' => ReportStatus::from($log->to_status)->label(),
                'actor' => $log->actor?->name,
                'note' => $log->note,
                'created_at' => $log->created_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }

    /** @return list<array{id: int, url: string}> */
    private static function photos(Report $report, AttachmentType $type): array
    {
        $photos = [];

        foreach ($report->attachments->where('type', $type)->sortBy('position') as $attachment) {
            $photos[] = ['id' => $attachment->id, 'url' => route('attachments.show', $attachment)];
        }

        return $photos;
    }
}
