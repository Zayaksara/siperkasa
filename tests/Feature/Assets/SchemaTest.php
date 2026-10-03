<?php

use App\Models\Asset;
use App\Models\Building;
use App\Models\Category;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\QueryException;

test('assets belong to a category and keep the raw BMN row as JSON', function () {
    $category = Category::factory()->create();
    $asset = Asset::factory()->for($category)->create(['bmn_data' => ['Merk' => 'IBM']]);

    expect($asset->category->is($category))->toBeTrue();
    expect($asset->fresh()->bmn_data['Merk'])->toBe('IBM');
    expect($category->isInUse())->toBeTrue();
});

test('a category with a default pic exposes it', function () {
    $pic = User::factory()->pic()->create();
    $category = Category::factory()->create(['default_pic_id' => $pic->id]);

    expect($category->defaultPic->is($pic))->toBeTrue();
    expect($pic->defaultCategories)->toHaveCount(1);
});

test('rooms belong to buildings and are unique per building', function () {
    $building = Building::factory()->create();
    Room::factory()->for($building)->create(['code' => '101']);

    expect($building->isInUse())->toBeTrue();
    expect(fn () => Room::factory()->for($building)->create(['code' => '101']))
        ->toThrow(QueryException::class);
});

test('an empty category and a building without rooms are not in use', function () {
    expect(Category::factory()->create()->isInUse())->toBeFalse();
    expect(Building::factory()->create()->isInUse())->toBeFalse();
});
