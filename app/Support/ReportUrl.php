<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Facades\Route;

final class ReportUrl
{
    /** The detail page (relative path, host-independent) of the report as seen by the recipient's role. */
    public static function for(Report $report, User $recipient): string
    {
        $name = match ($recipient->role) {
            UserRole::Admin => 'admin.reports.show',
            UserRole::Pic => 'pic.tasks.show',
            UserRole::User => 'reports.show',
        };

        return Route::has($name) ? route($name, $report, false) : route('dashboard', [], false);
    }
}
