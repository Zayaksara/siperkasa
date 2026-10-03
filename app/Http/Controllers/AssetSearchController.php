<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssetSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $like = addcslashes($q, '%_\\');

        if (mb_strlen($q) < 2 || trim($like, '\\%_') === '') {
            return response()->json(['data' => []]);
        }

        $assets = Asset::query()
            ->with('category')
            ->where(fn ($query) => $query->where('asset_code', 'like', "{$like}%")->orWhere('name', 'like', "%{$like}%"))
            ->orderBy('name')
            ->limit(20)
            ->get()
            ->map(fn (Asset $asset) => [
                'id' => $asset->id,
                'asset_code' => $asset->asset_code,
                'name' => $asset->name,
                'brand' => $asset->brand,
                'category' => $asset->category?->name,
            ]);

        return response()->json(['data' => $assets]);
    }
}
