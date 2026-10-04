<?php

namespace App\Support;

use App\Enums\Priority;
use App\Enums\ReportStatus;
use App\Models\Report;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** The admin's report filters; shared by the list, dashboard drill-downs and exports. */
final class ReportFilter
{
    /** @param array<string, mixed> $values */
    public function __construct(public readonly array $values) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(ReportStatus::class)],
            'category_id' => ['nullable', 'integer'],
            'building_id' => ['nullable', 'integer'],
            'pic_id' => ['nullable', 'integer'],
            'priority' => ['nullable', Rule::enum(Priority::class)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'overdue' => ['nullable', 'boolean'],
            'q' => ['nullable', 'string', 'max:100'],
        ];
    }

    /** @return array<string, string> */
    public static function attributes(): array
    {
        return [
            'status' => 'status',
            'category_id' => 'kategori',
            'building_id' => 'gedung',
            'pic_id' => 'PIC',
            'priority' => 'prioritas',
            'from' => 'tanggal mulai',
            'to' => 'tanggal akhir',
            'overdue' => 'filter terlambat',
            'q' => 'kata kunci',
        ];
    }

    public static function fromRequest(Request $request): self
    {
        return new self($request->validate(self::rules(), [], self::attributes()));
    }

    /**
     * @param  Builder<Report>  $query
     * @return Builder<Report>
     */
    public function apply(Builder $query): Builder
    {
        $v = $this->values;

        return $query
            ->when($v['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($v['category_id'] ?? null, fn ($q, $id) => $q->where('category_id', $id))
            ->when($v['building_id'] ?? null, fn ($q, $id) => $q->whereHas('room', fn ($room) => $room->where('building_id', $id)))
            ->when($v['pic_id'] ?? null, fn ($q, $id) => $q->where('assigned_pic_id', $id))
            ->when($v['priority'] ?? null, fn ($q, $priority) => $q->whereRaw('COALESCE(admin_priority, user_priority) = ?', [$priority]))
            ->when($v['from'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($v['to'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
            ->when($v['overdue'] ?? false, fn ($q) => $q->overdue())
            ->when($v['q'] ?? null, function ($q, string $term) {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $q->where(fn ($inner) => $inner->where('ticket_number', 'like', $like)->orWhere('title', 'like', $like));
            });
    }
}
