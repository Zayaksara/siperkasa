<?php

namespace App\Support;

use App\Enums\Priority;
use App\Enums\ReportStatus;
use App\Models\Building;
use App\Models\Category;
use App\Models\Report;
use App\Models\User;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\EmptyCell;
use OpenSpout\Common\Entity\Cell\NumericCell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;

/** Column layout and cell building shared by the admin report exports. */
final class ReportExport
{
    public const HEADERS = [
        'Tiket', 'Tanggal lapor', 'Pelapor', 'NIM/NIP', 'Judul', 'Kategori', 'Gedung', 'Ruang', 'Kode aset',
        'Urgensi pelapor', 'Prioritas admin', 'Status', 'PIC', 'SLA', 'Terlambat', 'Tanggal selesai',
        'Tindakan perbaikan', 'Biaya', 'Vendor',
    ];

    /** Relations read by row(); keeps the export free of N+1 queries. */
    public const RELATIONS = [
        'user:id,name,nim_nip,deleted_at',
        'category:id,name',
        'room.building:id,name',
        'asset:id,asset_code',
        'assignedPic:id,name,deleted_at',
    ];

    public static function headerRow(): Row
    {
        return new Row(array_map(fn (string $header) => new StringCell($header, null), self::HEADERS));
    }

    public static function emptyRow(): Row
    {
        return new Row([new StringCell('Tidak ada data', null)]);
    }

    /**
     * Every user-provided text is a StringCell, so it can never be read as a formula (and shows no apostrophe).
     */
    public static function row(Report $report): Row
    {
        $values = [
            $report->ticket_number,
            $report->created_at?->format('Y-m-d H:i'),
            self::person($report->user),
            $report->user?->nim_nip,
            $report->title,
            $report->category?->name,
            $report->room?->building?->name,
            $report->room?->name,
            $report->asset?->asset_code,
            $report->user_priority->label(),
            $report->admin_priority?->label(),
            $report->status->label(),
            self::person($report->assignedPic),
            $report->sla_date?->format('Y-m-d H:i'),
            $report->isOverdue() ? 'Ya' : 'Tidak',
            $report->completed_on?->toDateString(),
            $report->repair_action,
            $report->repair_cost === null ? null : (float) $report->repair_cost,
            $report->vendor_name,
        ];

        return new Row(array_map(self::cell(...), $values));
    }

    private static function cell(string|float|null $value): Cell
    {
        return match (true) {
            $value === null || $value === '' => new EmptyCell(null, null),
            is_float($value) => new NumericCell($value, null),
            default => new StringCell($value, null),
        };
    }

    private static function person(?User $user): ?string
    {
        if ($user === null) {
            return null;
        }

        return $user->trashed() ? "{$user->name} (terhapus)" : $user->name;
    }

    /** Short location text for the PDF table. */
    public static function location(Report $report): string
    {
        return ($report->room?->building?->name ?? '-').', '.($report->room?->name ?? '-');
    }

    /** @param array<string, mixed> $values validated ReportFilter values */
    public static function filterText(array $values): string
    {
        $parts = [];

        if (! empty($values['status'])) {
            $parts[] = 'Status: '.ReportStatus::from($values['status'])->label();
        }
        if (! empty($values['category_id'])) {
            $parts[] = 'Kategori: '.(Category::find((int) $values['category_id'])?->name ?? $values['category_id']);
        }
        if (! empty($values['building_id'])) {
            $parts[] = 'Gedung: '.(Building::find((int) $values['building_id'])?->name ?? $values['building_id']);
        }
        if (! empty($values['pic_id'])) {
            $parts[] = 'PIC: '.(User::withTrashed()->find((int) $values['pic_id'])?->name ?? $values['pic_id']);
        }
        if (! empty($values['priority'])) {
            $parts[] = 'Prioritas: '.Priority::from($values['priority'])->label();
        }
        if (! empty($values['from'])) {
            $parts[] = 'Dari: '.$values['from'];
        }
        if (! empty($values['to'])) {
            $parts[] = 'Sampai: '.$values['to'];
        }
        if (! empty($values['overdue'])) {
            $parts[] = 'Hanya terlambat';
        }
        if (! empty($values['q'])) {
            $parts[] = 'Cari: '.$values['q'];
        }

        return $parts === [] ? 'Semua laporan' : implode(' · ', $parts);
    }
}
