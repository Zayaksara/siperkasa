<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveBuildingRequest;
use App\Models\Building;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BuildingController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Building::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $q = $filters['q'] ?? '';
        $like = addcslashes($q, '%_\\');

        return Inertia::render('admin/buildings/Index', [
            'buildings' => Building::query()
                ->withCount('rooms')
                ->when($q !== '', fn ($query) => $query->where(fn ($inner) => $inner->where('code', 'like', "{$like}%")->orWhere('name', 'like', "%{$like}%")))
                ->orderBy('code')
                ->get(['id', 'code', 'name']),
            'filters' => $q !== '' ? ['q' => $q] : [],
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
