<?php

use App\Http\Controllers\DashboardController;
use App\Models\Asset;
use App\Models\Report;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return inertia('Welcome', [
        'stats' => [
            'total_reports' => Report::count(),
            'total_completed' => Report::where('status', 'Closed')->count(),
            'total_in_progress' => Report::whereIn('status', ['Assigned', 'In_Progress'])->count(),
            'total_assets' => Asset::count(),
        ],
    ]);
})->name('home');

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::middleware('role:user')->prefix('user')->name('user.')->group(function () {
        Route::inertia('dashboard', 'user/Dashboard')->name('dashboard');
    });

    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::inertia('dashboard', 'admin/Dashboard')->name('dashboard');
    });

    Route::middleware('role:pic')->prefix('pic')->name('pic.')->group(function () {
        Route::inertia('dashboard', 'pic/Dashboard')->name('dashboard');
    });
});

require __DIR__.'/settings.php';
