<?php

namespace App\Support;

use App\Models\Report;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

final class TicketNumber
{
    /** Next ticket for the month of $now, e.g. SPK-2610-0001 (restarts monthly). */
    public static function next(?CarbonInterface $now = null): string
    {
        $prefix = 'SPK-'.($now ?? now())->format('ym').'-';

        return DB::transaction(function () use ($prefix) {
            $last = Report::query()
                ->where('ticket_number', 'like', $prefix.'%')
                ->orderByDesc('ticket_number')
                ->lockForUpdate()
                ->value('ticket_number');

            $sequence = $last === null ? 1 : ((int) substr($last, -4)) + 1;

            return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
        });
    }
}
