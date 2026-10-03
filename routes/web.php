<?php

use App\Enums\ReportStatus;
use App\Http\Controllers\Admin\AssetController;
use App\Http\Controllers\Admin\AssetImportController;
use App\Http\Controllers\Admin\BuildingController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\RoomController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserImportController;
use App\Http\Controllers\AssetSearchController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\User\ReportController;
use App\Models\Asset;
use App\Models\Report;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return inertia('Welcome', [
        'stats' => [
            'total_reports' => Report::count(),
            'total_completed' => Report::where('status', ReportStatus::Closed)->count(),
            'total_in_progress' => Report::whereIn('status', [ReportStatus::Assigned, ReportStatus::InProgress])->count(),
            'total_assets' => Asset::count(),
        ],
    ]);
})->name('home');

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('assets/search', AssetSearchController::class)->name('assets.search');
    Route::get('attachments/{attachment}', AttachmentController::class)->name('attachments.show');

    Route::middleware('role:user')->prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::get('create', [ReportController::class, 'create'])->name('create');
        Route::post('/', [ReportController::class, 'store'])->name('store');
        Route::get('{report}', [ReportController::class, 'show'])->name('show');
        Route::get('{report}/edit', [ReportController::class, 'edit'])->name('edit');
        Route::put('{report}', [ReportController::class, 'update'])->name('update');
        Route::post('{report}/cancel', [ReportController::class, 'cancel'])->name('cancel');
    });

    Route::middleware('role:user')->prefix('user')->name('user.')->group(function () {
        Route::inertia('dashboard', 'user/Dashboard')->name('dashboard');
    });

    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::inertia('dashboard', 'admin/Dashboard')->name('dashboard');
        Route::post('users/import', [UserImportController::class, 'store'])->name('users.import');
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::patch('users/{user}/active', [UserController::class, 'active'])->name('users.active');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
        Route::post('users/{user}/restore', [UserController::class, 'restore'])->withTrashed()->name('users.restore');
        Route::put('users/{user}/password', [UserController::class, 'password'])->name('users.password');
        Route::get('assets', [AssetController::class, 'index'])->name('assets.index');
        Route::get('assets/{asset}', [AssetController::class, 'show'])->name('assets.show');
        Route::get('asset-imports', [AssetImportController::class, 'index'])->name('asset-imports.index');
        Route::post('asset-imports', [AssetImportController::class, 'store'])->name('asset-imports.store');
        Route::resource('categories', CategoryController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('buildings', BuildingController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
        Route::post('buildings/{building}/rooms', [RoomController::class, 'store'])->name('buildings.rooms.store');
        Route::put('rooms/{room}', [RoomController::class, 'update'])->name('rooms.update');
        Route::delete('rooms/{room}', [RoomController::class, 'destroy'])->name('rooms.destroy');
    });

    Route::middleware('role:pic')->prefix('pic')->name('pic.')->group(function () {
        Route::inertia('dashboard', 'pic/Dashboard')->name('dashboard');
    });
});

require __DIR__.'/settings.php';
