<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveRoomRequest;
use App\Models\Building;
use App\Models\Room;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class RoomController extends Controller
{
    public function store(SaveRoomRequest $request, Building $building): RedirectResponse
    {
        Gate::authorize('create', Room::class);

        $building->rooms()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Ruang ditambahkan.']);

        return back();
    }

    public function update(SaveRoomRequest $request, Room $room): RedirectResponse
    {
        Gate::authorize('update', $room);

        $room->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Ruang diperbarui.']);

        return back();
    }

    public function destroy(Room $room): RedirectResponse
    {
        Gate::authorize('delete', $room);

        if ($room->isInUse()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Ruang masih dipakai laporan dan tidak bisa dihapus.']);

            return back();
        }

        $room->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Ruang dihapus.']);

        return back();
    }
}
