<?php

namespace App\Support;

use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\User;
use Carbon\CarbonInterface;

final class PicPerformance
{
    /**
     * On-time performance per PIC for validated reports in [$from 00:00, $to 23:59:59].
     * Reports count toward their current assigned PIC. One grouped query, no N+1.
     *
     * @return array{summary: array<string, mixed>, pics: list<array<string, mixed>>}
     */
    public function forRange(CarbonInterface $from, CarbonInterface $to): array
    {
        $final = ReportStatus::finalValues();
        $finalIn = implode(',', array_fill(0, count($final), '?'));
        $closed = ReportStatus::Closed->value;
        $now = now();

        $rows = Report::query()
            ->whereNotNull('assigned_pic_id')->whereNotNull('sla_date')
            ->whereNotIn('status', [ReportStatus::Cancelled, ReportStatus::Rejected, ReportStatus::Pending])
            ->whereBetween('validated_at', [$from->startOfDay(), $to->endOfDay()])
            ->selectRaw(
                'assigned_pic_id, COUNT(*) as total,'
                .'SUM(CASE WHEN status = ? AND closed_at <= sla_date THEN 1 ELSE 0 END) as on_time,'
                .'SUM(CASE WHEN status = ? AND closed_at > sla_date THEN 1 ELSE 0 END) as late_closed,'
                ."SUM(CASE WHEN status NOT IN ({$finalIn}) AND sla_date < ? THEN 1 ELSE 0 END) as late_open,"
                ."SUM(CASE WHEN status NOT IN ({$finalIn}) AND sla_date >= ? THEN 1 ELSE 0 END) as on_track,"
                .'AVG(CASE WHEN status = ? AND closed_at > sla_date THEN TIMESTAMPDIFF(SECOND, sla_date, closed_at) END) as avg_late_seconds',
                [$closed, $closed, ...$final, $now, ...$final, $now, $closed],
            )
            ->groupBy('assigned_pic_id')
            ->get();

        $users = User::withTrashed()->whereIn('id', $rows->pluck('assigned_pic_id'))->get(['id', 'name', 'is_active', 'deleted_at'])->keyBy('id');

        $pics = $rows->map(function (Report $row) use ($users) {
            $user = $users->get($row->getAttribute('assigned_pic_id'));
            $avg = $row->getAttribute('avg_late_seconds');

            return [
                'id' => (int) $row->getAttribute('assigned_pic_id'),
                'name' => (string) ($user?->name ?? '-'),
                'trashed' => (bool) $user?->trashed(),
                'active' => (bool) ($user?->is_active),
                ...$this->counts($row),
                'avg_late_hours' => $avg === null ? null : round((float) $avg / 3600, 1),
            ];
        })->sort(function (array $a, array $b) {
            if ($a['on_time_rate'] === null || $b['on_time_rate'] === null) {
                if ($a['on_time_rate'] !== $b['on_time_rate']) {
                    return $a['on_time_rate'] === null ? 1 : -1;
                }
            } elseif ($a['on_time_rate'] !== $b['on_time_rate']) {
                return $a['on_time_rate'] <=> $b['on_time_rate'];
            }

            return strcasecmp($a['name'], $b['name']);
        })->values();

        $sum = fn (string $key) => (int) $pics->sum($key);

        return [
            'summary' => $this->withRate([
                'total' => $sum('total'),
                'on_time' => $sum('on_time'),
                'late_closed' => $sum('late_closed'),
                'late_open' => $sum('late_open'),
                'on_track' => $sum('on_track'),
            ]),
            'pics' => $pics->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function counts(Report $row): array
    {
        return $this->withRate([
            'total' => (int) $row->getAttribute('total'),
            'on_time' => (int) $row->getAttribute('on_time'),
            'late_closed' => (int) $row->getAttribute('late_closed'),
            'late_open' => (int) $row->getAttribute('late_open'),
            'on_track' => (int) $row->getAttribute('on_track'),
        ]);
    }

    /**
     * @param  array<string, int>  $counts
     * @return array<string, mixed>
     */
    private function withRate(array $counts): array
    {
        $judged = $counts['on_time'] + $counts['late_closed'] + $counts['late_open'];

        return $counts + ['on_time_rate' => $judged === 0 ? null : round($counts['on_time'] / $judged * 100, 1)];
    }
}
