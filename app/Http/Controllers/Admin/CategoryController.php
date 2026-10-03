<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveCategoryRequest;
use App\Models\Category;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Category::class);

        return Inertia::render('admin/categories/Index', [
            'categories' => Category::query()->with('defaultPic:id,name')->withCount('assets')->orderBy('name')->get()
                ->map(fn (Category $category) => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'default_pic_id' => $category->default_pic_id,
                    'default_pic_name' => $category->defaultPic?->name,
                    'assets_count' => $category->assets_count,
                ]),
            'pics' => User::query()->where('role', UserRole::Pic)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(SaveCategoryRequest $request): RedirectResponse
    {
        Gate::authorize('create', Category::class);

        Category::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kategori ditambahkan.']);

        return back();
    }

    public function update(SaveCategoryRequest $request, Category $category): RedirectResponse
    {
        Gate::authorize('update', $category);

        $category->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kategori diperbarui.']);

        return back();
    }

    public function destroy(Category $category): RedirectResponse
    {
        Gate::authorize('delete', $category);

        if ($category->isInUse()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Kategori masih dipakai dan tidak bisa dihapus.']);

            return back();
        }

        $category->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kategori dihapus.']);

        return back();
    }
}
