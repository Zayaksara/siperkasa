<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneReadNotifications extends Command
{
    protected $signature = 'notifications:prune {--days=180 : Delete read notifications older than this many days}';

    protected $description = 'Delete read notifications older than N days (unread ones are never deleted)';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));

        $deleted = DB::table('notifications')
            ->whereNotNull('read_at')
            ->where('read_at', '<', now()->subDays($days))
            ->delete();

        $this->info("{$deleted} notifikasi terbaca dihapus (lebih dari {$days} hari).");

        return self::SUCCESS;
    }
}
