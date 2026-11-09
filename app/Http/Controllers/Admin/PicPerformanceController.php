<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\PicPerformance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PicPerformanceController extends Controller
{
    private const MAX_RANGE_DAYS = 366;

    public function __invoke(Request $request, PicPerformance $performance): Response
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ], [], ['from' => 'tanggal mulai', 'to' => 'tanggal akhir']);

        $to = Date::parse($data['to'] ?? now()->toDateString())->startOfDay();
        $from = Date::parse($data['from'] ?? $to->subDays(29)->toDateString())->startOfDay();

        if ($from->diffInDays($to) > self::MAX_RANGE_DAYS - 1) {
            throw ValidationException::withMessages(['to' => 'Rentang tanggal maksimal 1 tahun.']);
        }

        return Inertia::render('admin/pic-performance/Index', [
            'performance' => $performance->forRange($from, $to),
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
        ]);
    }
}
