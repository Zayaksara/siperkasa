<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AssetController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Asset::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer'],
            'golongan' => ['nullable', 'in:IT,NON-IT'],
            'condition' => ['nullable', 'string', 'max:50'],
        ]);

        $assets = Asset::query()
            ->with('category')
            ->when($filters['q'] ?? null, function ($query, string $q) {
                $like = addcslashes($q, '%_\\');
                $query->where(fn ($inner) => $inner->where('asset_code', 'like', "{$like}%")->orWhere('name', 'like', "%{$like}%"));
            })
            ->when($filters['category_id'] ?? null, fn ($query, $id) => $query->where('category_id', $id))
            ->when($filters['golongan'] ?? null, fn ($query, $golongan) => $query->where('golongan', $golongan))
            ->when($filters['condition'] ?? null, fn ($query, $condition) => $query->where('condition', $condition))
            ->orderBy('asset_code')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Asset $asset) => [
                'id' => $asset->id,
                'asset_code' => $asset->asset_code,
                'name' => $asset->name,
                'brand' => $asset->brand,
                'golongan' => $asset->golongan,
                'condition' => $asset->condition,
                'category' => $asset->category?->name,
            ]);

        return Inertia::render('admin/assets/Index', [
            'assets' => $assets,
            'filters' => $filters,
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'conditions' => Asset::query()->whereNotNull('condition')->distinct()->orderBy('condition')->pluck('condition'),
        ]);
    }

    public function show(Asset $asset): Response
    {
        Gate::authorize('view', $asset);

        return Inertia::render('admin/assets/Show', [
            'asset' => [
                'id' => $asset->id,
                'asset_code' => $asset->asset_code,
                'name' => $asset->name,
                'brand' => $asset->brand,
                'type' => $asset->type,
                'jenis_bmn' => $asset->jenis_bmn,
                'golongan' => $asset->golongan,
                'condition' => $asset->condition,
                'acquired_at' => $asset->acquired_at?->toDateString(),
                'acquisition_value' => $asset->acquisition_value,
                'category' => $asset->category?->name,
                'bmn_data' => $asset->bmn_data,
            ],
        ]);
    }
}
