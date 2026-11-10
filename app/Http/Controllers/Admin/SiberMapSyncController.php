<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Support\SiberMap\SiberMapException;
use App\Support\SiberMap\SiberMapSync;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class SiberMapSyncController extends Controller
{
    public function __invoke(SiberMapSync $sync): RedirectResponse
    {
        Gate::authorize('create', Building::class);

        try {
            $result = $sync->run();
        } catch (SiberMapException) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Gagal menghubungi SiberMap. Coba lagi nanti.']);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "Sinkron SiberMap selesai: {$result->buildingsUpserted} gedung, {$result->roomsUpserted} ruang."]);

        return back();
    }
}
