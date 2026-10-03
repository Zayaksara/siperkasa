<?php

use App\Models\Building;
use App\Models\Room;
use App\Models\User;

beforeEach(fn () => $this->admin = User::factory()->admin()->create());

test('admin manages buildings', function () {
    $this->actingAs($this->admin)->post(route('admin.buildings.store'), ['code' => 'GA', 'name' => 'Gedung A'])->assertRedirect();
    $building = Building::where('code', 'GA')->firstOrFail();

    $this->actingAs($this->admin)->put(route('admin.buildings.update', $building), ['code' => 'GA', 'name' => 'Gedung Rektorat'])->assertRedirect();
    expect($building->fresh()->name)->toBe('Gedung Rektorat');

    $this->actingAs($this->admin)->delete(route('admin.buildings.destroy', $building));
    expect(Building::count())->toBe(0);
});

test('building codes are unique', function () {
    Building::factory()->create(['code' => 'GA']);

    $this->actingAs($this->admin)->post(route('admin.buildings.store'), ['code' => 'GA', 'name' => 'Lain'])
        ->assertSessionHasErrors('code');
});

test('a building that still has rooms can not be deleted', function () {
    $building = Building::factory()->create();
    Room::factory()->for($building)->create();

    $this->actingAs($this->admin)->delete(route('admin.buildings.destroy', $building));

    expect($building->fresh())->not->toBeNull();
});

test('the building page lists its rooms', function () {
    $building = Building::factory()->create();
    Room::factory()->count(2)->for($building)->create();

    $this->actingAs($this->admin)->get(route('admin.buildings.show', $building))
        ->assertInertia(fn ($page) => $page->component('admin/buildings/Show')->has('rooms', 2));
});

test('admin manages rooms and codes are unique per building only', function () {
    $a = Building::factory()->create();
    $b = Building::factory()->create();

    $this->actingAs($this->admin)->post(route('admin.buildings.rooms.store', $a), ['code' => '101', 'name' => 'Ruang 101', 'floor' => 1])->assertRedirect();
    $this->actingAs($this->admin)->post(route('admin.buildings.rooms.store', $a), ['code' => '101', 'name' => 'Duplikat'])->assertSessionHasErrors('code');
    $this->actingAs($this->admin)->post(route('admin.buildings.rooms.store', $b), ['code' => '101', 'name' => 'Ruang 101 B'])->assertSessionHasNoErrors();

    $room = Room::where('building_id', $a->id)->firstOrFail();
    $this->actingAs($this->admin)->put(route('admin.rooms.update', $room), ['code' => '101', 'name' => 'Lab Komputer', 'floor' => 2])->assertSessionHasNoErrors();
    expect($room->fresh()->name)->toBe('Lab Komputer');

    $this->actingAs($this->admin)->delete(route('admin.rooms.destroy', $room));
    expect($room->fresh())->toBeNull();
});

test('non-admins are forbidden', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.buildings.index'))->assertForbidden();
});
