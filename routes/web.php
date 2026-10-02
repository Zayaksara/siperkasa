<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return inertia('Welcome', [
        'stats' => [
            'total_reports' => \App\Models\Report::count(),
            'total_completed' => \App\Models\Report::where('status', 'Closed')->count(),
            'total_in_progress' => \App\Models\Report::whereIn('status', ['Assigned', 'In_Progress'])->count(),
            'total_assets' => \App\Models\Asset::count(),
        ]
    ]);
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
