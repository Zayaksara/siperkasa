<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Suggests report categories for a free-text query (category picker combobox).
 *
 * A category matches by its own name, by a manual keyword in config('siperkasa.category_keywords')
 * (items missing from the BMN list), or by the asset names filed under it. Categories are ranked by
 * number of matches; each carries up to 3 distinct matched names as `examples`. Nothing matching
 * (or a query under 2 characters) yields only the 'Lain-lain' fallback.
 */
class CategorySearchController extends Controller
{
    private const MAX_RESULTS = 8;

    private const MAX_EXAMPLES = 3;

    private const FALLBACK = 'Lain-lain';

    public function __invoke(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $like = addcslashes($q, '%_\\');

        if (mb_strlen($q) < 2 || trim($like, '\\%_') === '') {
            return $this->fallback();
        }

        $categories = Category::orderBy('name')->get(['id', 'name'])->keyBy('id');
        $byName = $categories->keyBy('name');

        /** @var array<int, array{score: int, examples: array<int, string>}> $hits */
        $hits = [];
        $add = function (int $categoryId, string $example, int $weight) use (&$hits): void {
            $hits[$categoryId] ??= ['score' => 0, 'examples' => []];
            $hits[$categoryId]['score'] += $weight;

            if ($example !== '' && ! in_array($example, $hits[$categoryId]['examples'], true)) {
                $hits[$categoryId]['examples'][] = $example;
            }
        };

        foreach ($categories as $category) {
            if (Str::contains($category->name, $q, ignoreCase: true)) {
                $add($category->id, '', 1);
            }
        }

        foreach ((array) config('siperkasa.category_keywords') as $name => $keywords) {
            foreach ($keywords as $keyword) {
                if (isset($byName[$name]) && Str::contains($keyword, $q, ignoreCase: true)) {
                    $add($byName[$name]->id, $keyword, 1);
                }
            }
        }

        $assetRows = Asset::query()
            ->toBase()
            ->where('name', 'like', "%{$like}%")
            ->groupBy('category_id', 'name')
            ->orderByDesc('total')
            ->get(['category_id', 'name', \DB::raw('COUNT(*) as total')]);

        foreach ($assetRows as $row) {
            $add($row->category_id, $row->name, (int) $row->total);
        }

        if ($hits === []) {
            return $this->fallback();
        }

        $data = collect($hits)
            ->filter(fn (array $hit, int $id) => $categories->has($id))
            ->sortByDesc('score')
            ->take(self::MAX_RESULTS)
            ->map(fn (array $hit, int $id) => [
                'id' => $id,
                'name' => $categories[$id]->name,
                'examples' => array_slice($hit['examples'], 0, self::MAX_EXAMPLES),
            ])
            ->values();

        return response()->json(['data' => $data]);
    }

    private function fallback(): JsonResponse
    {
        $category = Category::where('name', self::FALLBACK)->first(['id', 'name']);

        return response()->json(['data' => $category === null ? [] : [
            ['id' => $category->id, 'name' => $category->name, 'examples' => []],
        ]]);
    }
}
