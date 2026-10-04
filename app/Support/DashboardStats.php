<?php

namespace App\Support;

use App\Enums\ReportStatus;
use App\Enums\UserRole;
use App\Models\Report;
use App\Models\User;
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
            'trend' => $this->weeklyTrend(),
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

    /** @return list<array{start: string, label: string, created: int, closed: int}> */
    private function weeklyTrend(): array
    {
        $first = now()->startOfWeek()->subWeeks(11);
        $created = $this->weeklyCounts('created_at', $first);
        $closed = $this->weeklyCounts('closed_at', $first);

        $weeks = [];
        for ($i = 0; $i < 12; $i++) {
            $start = $first->addWeeks($i);
            $key = $this->weekKey($start);
            $weeks[] = [
                'start' => $start->toDateString(),
                'label' => $start->format('d/m'),
                'created' => $created[$key] ?? 0,
                'closed' => $closed[$key] ?? 0,
            ];
        }

        return $weeks;
    }

    /**
     * Report count per ISO week (YEARWEEK mode 3) since $first, grouped in the DB.
     *
     * @param  literal-string  $column
     * @return array<int, int>
     */
    private function weeklyCounts(string $column, CarbonInterface $first): array
    {
        return Report::query()
            ->whereNotNull($column)->where($column, '>=', $first)
            ->selectRaw('YEARWEEK('.$column.', 3) as week_key, COUNT(*) as total')
            ->groupBy('week_key')
            ->pluck('total', 'week_key')
            ->mapWithKeys(fn ($total, $key) => [(int) $key => (int) $total])
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
