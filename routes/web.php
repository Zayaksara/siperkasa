<?php

use App\Http\Controllers\Admin\AssetController;
use App\Http\Controllers\Admin\AssetImportController;
use App\Http\Controllers\AssetSearchController;
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
    Route::get('assets/search', AssetSearchController::class)->name('assets.search');

    Route::middleware('role:user')->prefix('user')->name('user.')->group(function () {
        Route::inertia('dashboard', 'user/Dashboard')->name('dashboard');
    });

    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::inertia('dashboard', 'admin/Dashboard')->name('dashboard');
        Route::get('assets', [AssetController::class, 'index'])->name('assets.index');
        Route::get('assets/{asset}', [AssetController::class, 'show'])->name('assets.show');
        Route::get('asset-imports', [AssetImportController::class, 'index'])->name('asset-imports.index');
        Route::post('asset-imports', [AssetImportController::class, 'store'])->name('asset-imports.store');
    });

    Route::middleware('role:pic')->prefix('pic')->name('pic.')->group(function () {
        Route::inertia('dashboard', 'pic/Dashboard')->name('dashboard');
    });
});

require __DIR__.'/settings.php';
