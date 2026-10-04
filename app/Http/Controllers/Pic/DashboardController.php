<?php

namespace App\Http\Controllers\Pic;

use App\Http\Controllers\Controller;
use App\Support\DashboardStats;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardStats $stats): Response
    {
        return Inertia::render('pic/Dashboard', ['stats' => $stats->forPic($request->user())]);
    }
}
