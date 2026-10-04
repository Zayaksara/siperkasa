<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class ThrottleReportSubmissions
{
    public function handle(Request $request, Closure $next): Response
    {
        $limit = 10;
        $decayMinutes = 60;
        $key = 'report-submissions:'.$request->user()?->id;

        if (RateLimiter::tooManyAttempts($key, $limit)) {
            return back()->withErrors([
                'title' => 'Terlalu banyak laporan dalam satu jam. Coba lagi nanti.',
            ]);
        }

        RateLimiter::hit($key, $decayMinutes * 60);

        return $next($request);
    }
}
