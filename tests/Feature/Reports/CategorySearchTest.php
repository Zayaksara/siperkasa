<?php

use App\Models\Asset;
use App\Models\Category;
use App\Models\User;

beforeEach(function () {
    $this->lainLain = Category::firstOrCreate(['name' => 'Lain-lain']);
    $this->bangunan = Category::firstOrCreate(['name' => 'Bangunan & Sarana']);
});

test('guests can not search categories', function () {
    $this->getJson(route('categories.search', ['q' => 'proyektor']))->assertUnauthorized();
});

test('an asset name match returns its category with example names', function (string $state) {
    $elektronik = Category::factory()->create(['name' => 'Elektronik Uji']);
    Asset::factory()->count(2)->create(['name' => 'Proyektor Epson', 'category_id' => $elektronik->id]);
    Asset::factory()->create(['name' => 'Proyektor Sony', 'category_id' => $elektronik->id]);
    Asset::factory()->create(['name' => 'Kursi Kuliah']);

    $this->actingAs(User::factory()->{$state}()->create())
        ->getJson(route('categories.search', ['q' => 'proyektor']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $elektronik->id)
        ->assertJsonPath('data.0.name', 'Elektronik Uji')
        ->assertJsonCount(2, 'data.0.examples');
})->with(['reporter', 'admin']);

test('examples are distinct and capped at 3', function () {
    $cat = Category::factory()->create();
    foreach (['Meja A', 'Meja B', 'Meja C', 'Meja D'] as $name) {
        Asset::factory()->count(2)->create(['name' => $name, 'category_id' => $cat->id]);
    }

    $examples = $this->actingAs(User::factory()->create())
        ->getJson(route('categories.search', ['q' => 'meja']))
        ->json('data.0.examples');

    expect($examples)->toHaveCount(3)->and(array_unique($examples))->toHaveCount(3);
});

test('a keyword matches its category', function () {
    $this->actingAs(User::factory()->create())
        ->getJson(route('categories.search', ['q' => 'plafon']))
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Bangunan & Sarana')
        ->assertJsonPath('data.0.examples.0', 'plafon');
});

test('a category name match is returned', function () {
    $this->actingAs(User::factory()->create())
        ->getJson(route('categories.search', ['q' => 'bangunan']))
        ->assertJsonPath('data.0.name', 'Bangunan & Sarana');
});

test('categories with more matching assets rank first', function () {
    $few = Category::factory()->create(['name' => 'Sedikit']);
    $many = Category::factory()->create(['name' => 'Banyak']);
    Asset::factory()->create(['name' => 'Layar Uji', 'category_id' => $few->id]);
    Asset::factory()->count(3)->create(['name' => 'Layar Uji', 'category_id' => $many->id]);

    $this->actingAs(User::factory()->create())
        ->getJson(route('categories.search', ['q' => 'layar uji']))
        ->assertJsonPath('data.0.id', $many->id)
        ->assertJsonPath('data.1.id', $few->id);
});

test('results are capped at 8 categories', function () {
    foreach (range(1, 10) as $i) {
        Asset::factory()->create(['name' => 'Gadget Uji', 'category_id' => Category::factory()]);
    }

    $this->actingAs(User::factory()->create())
        ->getJson(route('categories.search', ['q' => 'gadget']))
        ->assertJsonCount(8, 'data');
});

test('LIKE wildcards in the query are escaped', function () {
    Asset::factory()->create(['name' => 'Kursi Kuliah']);

    $this->actingAs(User::factory()->create())
        ->getJson(route('categories.search', ['q' => 'k%i']))
        ->assertJsonPath('data.0.name', 'Lain-lain')
        ->assertJsonCount(1, 'data');
});

test('no match, short or empty query falls back to Lain-lain', function (string $q) {
    $this->actingAs(User::factory()->create())
        ->getJson(route('categories.search', ['q' => $q]))
        ->assertOk()
        ->assertExactJson(['data' => [['id' => $this->lainLain->id, 'name' => 'Lain-lain', 'examples' => []]]]);
})->with(['zzzzqqq', 'a', '']);
