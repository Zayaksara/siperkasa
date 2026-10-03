<?php

use App\Models\Asset;
use App\Models\Category;
use App\Models\User;

test('admin browses assets with filters', function () {
    $laptops = Category::factory()->create(['name' => 'Komputer & Server']);
    Asset::factory()->for($laptops)->create(['name' => 'Laptop Dosen', 'golongan' => 'IT', 'condition' => 'Rusak Berat']);
    Asset::factory()->count(2)->create(['golongan' => 'NON-IT']);

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('admin.assets.index'))
        ->assertInertia(fn ($page) => $page->component('admin/assets/Index')->has('assets.data', 3));

    $this->actingAs($admin)->get(route('admin.assets.index', ['q' => 'laptop']))
        ->assertInertia(fn ($page) => $page->has('assets.data', 1));

    $this->actingAs($admin)->get(route('admin.assets.index', ['golongan' => 'IT']))
        ->assertInertia(fn ($page) => $page->has('assets.data', 1));

    $this->actingAs($admin)->get(route('admin.assets.index', ['category_id' => $laptops->id, 'condition' => 'Rusak Berat']))
        ->assertInertia(fn ($page) => $page->has('assets.data', 1));
});

test('asset detail shows the raw BMN data and offers no edit routes', function () {
    $asset = Asset::factory()->create(['bmn_data' => ['Merk' => 'IBM']]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.assets.show', $asset))
        ->assertInertia(fn ($page) => $page
            ->component('admin/assets/Show')
            ->where('asset.bmn_data.Merk', 'IBM'));

    $this->actingAs(User::factory()->admin()->create())
        ->put("/admin/assets/{$asset->id}", ['name' => 'x'])
        ->assertStatus(405);
});

test('non-admins can not browse assets', function () {
    $this->actingAs(User::factory()->pic()->create())->get(route('admin.assets.index'))->assertForbidden();
});
