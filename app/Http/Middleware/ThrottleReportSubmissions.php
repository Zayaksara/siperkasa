<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class ThrottleReportSubmissions
{
    /**
     * Check rate limit. Only count successful report creations; hit is done in controller.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $key = 'reports'.$request->user()?->id;

        if (RateLimiter::tooManyAttempts($key, 10)) {
            return back()->withErrors([
                'title' => 'Terlalu banyak laporan dalam satu jam. Coba lagi nanti.',
            ]);
        }

        return $next($request);
    }
}
