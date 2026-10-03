<?php

use App\Models\Asset;
use App\Models\Category;
use App\Models\User;

beforeEach(fn () => $this->admin = User::factory()->admin()->create());

test('admin sees categories with their pic and asset counts', function () {
    $pic = User::factory()->pic()->create();
    $category = Category::factory()->create(['name' => 'Elektronik', 'default_pic_id' => $pic->id]);
    Asset::factory()->count(2)->for($category)->create();

    $this->actingAs($this->admin)->get(route('admin.categories.index'))
        ->assertInertia(fn ($page) => $page
            ->component('admin/categories/Index')
            ->where('categories.0.name', 'Elektronik')
            ->where('categories.0.default_pic_name', $pic->name)
            ->where('categories.0.assets_count', 2)
            ->has('pics', 1));
});

test('admin creates and updates a category with a default pic', function () {
    $pic = User::factory()->pic()->create();

    $this->actingAs($this->admin)->post(route('admin.categories.store'), ['name' => 'Furnitur', 'default_pic_id' => $pic->id])
        ->assertRedirect();

    $category = Category::where('name', 'Furnitur')->firstOrFail();
    expect($category->default_pic_id)->toBe($pic->id);

    $this->actingAs($this->admin)->put(route('admin.categories.update', $category), ['name' => 'Furnitur Kampus', 'default_pic_id' => null])
        ->assertRedirect();

    expect($category->fresh()->name)->toBe('Furnitur Kampus')
        ->and($category->fresh()->default_pic_id)->toBeNull();
});

test('only active pic users can be the default pic', function (string $state, bool $active) {
    $user = User::factory()->{$state}()->create(['is_active' => $active]);

    $this->actingAs($this->admin)->post(route('admin.categories.store'), ['name' => 'X', 'default_pic_id' => $user->id])
        ->assertSessionHasErrors('default_pic_id');

    expect(Category::count())->toBe(0);
})->with([['reporter', true], ['admin', true], ['pic', false]]);

test('category names are unique', function () {
    Category::factory()->create(['name' => 'Elektronik']);

    $this->actingAs($this->admin)->post(route('admin.categories.store'), ['name' => 'Elektronik'])
        ->assertSessionHasErrors('name');
});

test('a category in use can not be deleted, an empty one can', function () {
    $used = Category::factory()->create();
    Asset::factory()->for($used)->create();
    $empty = Category::factory()->create();

    $this->actingAs($this->admin)->delete(route('admin.categories.destroy', $used));
    $this->actingAs($this->admin)->delete(route('admin.categories.destroy', $empty));

    expect($used->fresh())->not->toBeNull()
        ->and($empty->fresh())->toBeNull();
});

test('non-admins are forbidden', function (string $state) {
    $user = User::factory()->{$state}()->create();

    $this->actingAs($user)->get(route('admin.categories.index'))->assertForbidden();
    $this->actingAs($user)->post(route('admin.categories.store'), ['name' => 'X'])->assertForbidden();
})->with(['reporter', 'pic']);
