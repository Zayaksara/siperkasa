<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveBuildingRequest;
use App\Models\Building;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BuildingController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Building::class);

        return Inertia::render('admin/buildings/Index', [
            'buildings' => Building::query()->withCount('rooms')->orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    public function show(Building $building): Response
    {
        Gate::authorize('view', $building);

        return Inertia::render('admin/buildings/Show', [
            'building' => $building->only(['id', 'code', 'name']),
            'rooms' => $building->rooms()->orderBy('code')->get(['id', 'code', 'name', 'floor']),
        ]);
    }

    public function store(SaveBuildingRequest $request): RedirectResponse
    {
        Gate::authorize('create', Building::class);

        Building::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Gedung ditambahkan.']);

        return back();
    }

    public function update(SaveBuildingRequest $request, Building $building): RedirectResponse
    {
        Gate::authorize('update', $building);

        $building->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Gedung diperbarui.']);

        return back();
    }

    public function destroy(Building $building): RedirectResponse
    {
        Gate::authorize('delete', $building);

        if ($building->isInUse()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Gedung masih punya ruang. Hapus ruangnya dulu.']);

            return back();
        }

        $building->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Gedung dihapus.']);

        return to_route('admin.buildings.index');
    }
}
