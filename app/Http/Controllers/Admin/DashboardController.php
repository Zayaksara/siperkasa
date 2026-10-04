<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\DashboardStats;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    private const MAX_RANGE_DAYS = 366;

    public function __invoke(Request $request, DashboardStats $stats): Response
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ], [], ['from' => 'tanggal mulai', 'to' => 'tanggal akhir']);

        $to = Date::parse($data['to'] ?? now()->toDateString())->startOfDay();
        $from = Date::parse($data['from'] ?? $to->subDays(29)->toDateString())->startOfDay();

        // Longer ranges would build thousands of trend buckets (and cache them).
        if ($from->diffInDays($to) > self::MAX_RANGE_DAYS - 1) {
            throw ValidationException::withMessages(['to' => 'Rentang tanggal maksimal 1 tahun.']);
        }

        $key = "dashboard.admin.v2.{$from->toDateString()}.{$to->toDateString()}";

        return Inertia::render('admin/Dashboard', [
            'stats' => Cache::remember($key, now()->addMinutes(5), fn () => $stats->forAdmin($from, $to)),
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
        ]);
    }
}
