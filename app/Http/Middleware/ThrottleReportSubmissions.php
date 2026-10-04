<?php

namespace App\Http\Middleware;

use App\Support\ReportSubmissionLimit;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ThrottleReportSubmissions
{
    /**
     * Check rate limit. Only count successful report creations; hit is done in controller.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (ReportSubmissionLimit::tooMany($request->user())) {
            return back()->withErrors([
                'title' => 'Terlalu banyak laporan dalam satu jam. Coba lagi nanti.',
            ]);
        }

        return $next($request);
    }
}
