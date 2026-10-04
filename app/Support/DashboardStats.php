<?php

namespace App\Support;

use App\Enums\ReportStatus;
use App\Enums\UserRole;
use App\Models\Report;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class DashboardStats
{
    /** @return array<string, mixed> */
    public function forUser(User $user): array
    {
        $counts = Report::query()->where('user_id', $user->id)
            ->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        $sum = fn (ReportStatus ...$statuses) => (int) collect($statuses)->sum(fn (ReportStatus $status) => (int) ($counts[$status->value] ?? 0));

        return [
            'waiting' => $sum(ReportStatus::Pending),
            'processing' => $sum(ReportStatus::Assigned, ReportStatus::InProgress, ReportStatus::OnHold),
            'done' => $sum(ReportStatus::Closed),
            'ended' => $sum(ReportStatus::Rejected, ReportStatus::Cancelled),
            'recent' => $this->summaries(
                Report::query()->visibleTo($user)->latest('created_at')->latest('id')->limit(5),
            ),
        ];
    }

    /** @return array<string, mixed> */
    public function forPic(User $pic): array
    {
        $mine = fn () => Report::query()->visibleTo($pic);
        $active = [ReportStatus::Assigned, ReportStatus::InProgress, ReportStatus::OnHold];

        return [
            'new' => $mine()->where('status', ReportStatus::Assigned)->count(),
            'in_progress' => $mine()->where('status', ReportStatus::InProgress)->count(),
            'on_hold' => $mine()->where('status', ReportStatus::OnHold)->count(),
            'overdue' => $mine()->overdue()->count(),
            'done_this_month' => $mine()->where('status', ReportStatus::Closed)
                ->whereBetween('closed_at', [now()->startOfMonth(), now()->endOfMonth()])->count(),
            'upcoming' => $this->summaries($mine()->whereIn('status', $active)->orderBy('sla_date')->orderBy('id')->limit(5)),
        ];
    }

    /** @return array<string, mixed> */
    public function forAdmin(CarbonInterface $from, CarbonInterface $to): array
    {
        $range = fn () => Report::query()->whereBetween('reports.created_at', [$from->startOfDay(), $to->endOfDay()]);

        $byStatus = $range()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $counts = [];
        foreach (ReportStatus::cases() as $status) {
            $counts[$status->value] = (int) ($byStatus[$status->value] ?? 0);
        }

        /** @param literal-string $column */
        $avgHours = fn (string $column, ?ReportStatus $status = null) => $this->averageHours(
            $range()->when($status, fn (Builder $query) => $query->where('status', $status))->whereNotNull($column),
            $column,
        );

        return [
            'counts' => $counts,
            'total' => array_sum($counts),
            'overdue' => Report::overdue()->count(),
            'waiting_validation' => $counts[ReportStatus::Pending->value],
            'by_category' => collect($range()
                ->join('categories', 'categories.id', '=', 'reports.category_id')
                ->selectRaw('categories.id, categories.name, COUNT(*) as total')
                ->groupBy('categories.id', 'categories.name')->orderByDesc('total')->limit(10)->get())
                ->map(fn ($row) => ['id' => (int) $row['id'], 'name' => (string) $row['name'], 'total' => (int) $row['total']])->all(),
            'by_building' => collect($range()
                ->join('rooms', 'rooms.id', '=', 'reports.room_id')
                ->join('buildings', 'buildings.id', '=', 'rooms.building_id')
                ->selectRaw('buildings.id, buildings.name, COUNT(*) as total')
                ->groupBy('buildings.id', 'buildings.name')->orderByDesc('total')->limit(10)->get())
                ->map(fn ($row) => ['id' => (int) $row['id'], 'name' => (string) $row['name'], 'total' => (int) $row['total']])->all(),
            ...$this->trend($from, $to),
            'avg_completion_hours' => $avgHours('closed_at', ReportStatus::Closed),
            'avg_validation_hours' => $avgHours('validated_at'),
            'pic_load' => User::query()
                ->where('role', UserRole::Pic)->where('is_active', true)
                ->withCount(['assignedReports as active_tasks' => fn ($query) => $query->whereNotIn('status', ReportStatus::finalValues())])
                ->orderByDesc('active_tasks')->orderBy('name')->get(['id', 'name'])
                ->map(fn (User $pic) => ['id' => $pic->id, 'name' => $pic->name, 'active_tasks' => (int) $pic->getAttribute('active_tasks')])->all(),
        ];
    }

    /**
     * @param  Builder<Report>  $query
     * @param  literal-string  $endColumn
     */
    private function averageHours(Builder $query, string $endColumn): ?float
    {
        $seconds = $query->avg(DB::raw('TIMESTAMPDIFF(SECOND, reports.created_at, reports.'.$endColumn.')'));

        return $seconds === null ? null : (float) round($seconds / 3600.0, 1);
    }

    /** Ranges up to this many days get one trend bucket per day, longer ones one per ISO week. */
    private const DAILY_TREND_MAX_DAYS = 31;

    /**
     * Created/closed trend over [$from, $to]: per day up to 31 days, else per ISO week.
     * Buckets are zero-filled; counts are grouped in the DB and limited to the range.
     *
     * @return array{trend: list<array{start: string, label: string, created: int, closed: int}>, trend_granularity: 'day'|'week'}
     */
    private function trend(CarbonInterface $from, CarbonInterface $to): array
    {
        $first = CarbonImmutable::instance($from)->startOfDay();
        $last = CarbonImmutable::instance($to)->endOfDay();
        $daily = $first->diffInDays($last->startOfDay()) + 1 <= self::DAILY_TREND_MAX_DAYS;

        $created = $this->trendCounts('created_at', $first, $last, $daily);
        $closed = $this->trendCounts('closed_at', $first, $last, $daily);

        $buckets = [];
        $cursor = $daily ? $first : $first->startOfWeek(CarbonInterface::MONDAY);
        while ($cursor <= $last) {
            $key = $daily ? $cursor->toDateString() : $this->weekKey($cursor);
            $buckets[] = [
                'start' => $cursor->toDateString(),
                'label' => $cursor->format('d/m'),
                'created' => $created[$key] ?? 0,
                'closed' => $closed[$key] ?? 0,
            ];
            $cursor = $daily ? $cursor->addDay() : $cursor->addWeek();
        }

        return ['trend' => $buckets, 'trend_granularity' => $daily ? 'day' : 'week'];
    }

    /**
     * Report count per day (DATE) or per ISO week (YEARWEEK mode 3) within the range, grouped in the DB.
     *
     * @param  literal-string  $column
     * @return array<int|string, int>
     */
    private function trendCounts(string $column, CarbonInterface $first, CarbonInterface $last, bool $daily): array
    {
        $bucket = $daily ? 'DATE('.$column.')' : 'YEARWEEK('.$column.', 3)';

        return Report::query()
            ->whereNotNull($column)->whereBetween($column, [$first, $last])
            ->selectRaw($bucket.' as bucket_key, COUNT(*) as total')
            ->groupBy('bucket_key')
            ->pluck('total', 'bucket_key')
            ->mapWithKeys(fn ($total, $key) => [$daily ? (string) $key : (int) $key => (int) $total])
            ->all();
    }

    /** Same shape as MySQL YEARWEEK(date, 3): ISO year * 100 + ISO week. */
    private function weekKey(CarbonInterface $date): int
    {
        return (int) $date->format('oW');
    }

    /**
     * @param  Builder<Report>  $query
     * @return list<array<string, mixed>>
     */
    private function summaries(Builder $query): array
    {
        /** @var list<array<string, mixed>> $result */
        $result = $query->withSummary()
            ->get()->map(fn (Report $report) => ReportPresenter::summary($report))->values()->all();

        return $result;
    }
}
