<?php

use App\Models\Building;
use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Carbon;

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

test('filters buildings by code or name', function () {
    Building::factory()->create(['code' => 'GKB', 'name' => 'Gedung Kuliah Bersama']);
    Building::factory()->create(['code' => 'REK', 'name' => 'Rektorat']);

    $this->actingAs($this->admin)->get(route('admin.buildings.index', ['q' => 'kuliah']))
        ->assertInertia(fn ($page) => $page
            ->has('buildings', 1)
            ->where('buildings.0.code', 'GKB')
            ->where('filters.q', 'kuliah'));

    $this->actingAs($this->admin)->get(route('admin.buildings.index', ['q' => 'rek']))
        ->assertInertia(fn ($page) => $page
            ->has('buildings', 1)
            ->where('buildings.0.code', 'REK'));

    $this->actingAs($this->admin)->get(route('admin.buildings.index', ['q' => '%']))
        ->assertInertia(fn ($page) => $page->has('buildings', 0));
});

test('synced buildings and rooms can not be updated or deleted', function () {
    $building = Building::factory()->synced()->create(['name' => 'Rektorat']);
    $room = Room::factory()->synced()->for($building)->create(['name' => 'Aula']);

    $this->actingAs($this->admin)->put(route('admin.buildings.update', $building), ['code' => $building->code, 'name' => 'Ubah'])->assertForbidden();
    $this->actingAs($this->admin)->delete(route('admin.buildings.destroy', $building))->assertForbidden();
    $this->actingAs($this->admin)->put(route('admin.rooms.update', $room), ['code' => $room->code, 'name' => 'Ubah', 'floor' => 1])->assertForbidden();
    $this->actingAs($this->admin)->delete(route('admin.rooms.destroy', $room))->assertForbidden();

    expect($building->fresh()->name)->toBe('Rektorat')->and($room->fresh()->name)->toBe('Aula');
});

test('manual buildings and rooms stay editable next to synced ones', function () {
    $manual = Building::factory()->create(['name' => 'Gedung Manual']);
    $manualRoom = Room::factory()->for($manual)->create(['name' => 'Ruang Manual']);

    $this->actingAs($this->admin)->put(route('admin.buildings.update', $manual), ['code' => $manual->code, 'name' => 'Gedung Baru'])->assertSessionHasNoErrors();
    $this->actingAs($this->admin)->put(route('admin.rooms.update', $manualRoom), ['code' => $manualRoom->code, 'name' => 'Ruang Baru', 'floor' => 2])->assertSessionHasNoErrors();

    expect($manual->fresh()->name)->toBe('Gedung Baru')->and($manualRoom->fresh()->name)->toBe('Ruang Baru');
});

test('buildings can be filtered by campus and match campus name in search', function () {
    Building::factory()->synced()->create(['code' => 'SM-1', 'name' => 'Rektorat', 'campus_name' => 'Kampus 1']);
    Building::factory()->create(['code' => 'GA', 'name' => 'Gedung A', 'campus_name' => 'Kampus 2']);

    $this->actingAs($this->admin)->get(route('admin.buildings.index', ['campus' => 'Kampus 1']))
        ->assertInertia(fn ($page) => $page
            ->has('buildings', 1)
            ->where('buildings.0.code', 'SM-1')
            ->where('buildings.0.campus_name', 'Kampus 1')
            ->where('filters.campus', 'Kampus 1'));

    $this->actingAs($this->admin)->get(route('admin.buildings.index', ['q' => 'kampus 2']))
        ->assertInertia(fn ($page) => $page->has('buildings', 1)->where('buildings.0.code', 'GA'));
});

test('buildings index exposes campuses and the last sync time', function () {
    Building::factory()->create(['campus_name' => null]);
    Building::factory()->synced()->create(['campus_name' => 'Kampus 2', 'synced_at' => '2026-11-09 04:00:00']);
    Building::factory()->synced()->create(['campus_name' => 'Kampus 1', 'synced_at' => '2026-11-08 04:00:00']);

    $this->actingAs($this->admin)->get(route('admin.buildings.index'))
        ->assertInertia(fn ($page) => $page
            ->where('campuses', ['Kampus 1', 'Kampus 2'])
            ->where('lastSyncedAt', Carbon::parse('2026-11-09 04:00:00')->toISOString()));
});

test('buildings index has no last sync time without synced buildings', function () {
    Building::factory()->create();

    $this->actingAs($this->admin)->get(route('admin.buildings.index'))
        ->assertInertia(fn ($page) => $page->where('lastSyncedAt', null)->where('campuses', []));
});

test('building page exposes sibermap fields and room floor names', function () {
    $building = Building::factory()->synced()->create(['campus_name' => 'Kampus 3', 'sibermap_id' => 77]);
    Room::factory()->synced()->for($building)->create(['floor_name' => 'Lt 2 Gedung']);

    $this->actingAs($this->admin)->get(route('admin.buildings.show', $building))
        ->assertInertia(fn ($page) => $page
            ->where('building.campus_name', 'Kampus 3')
            ->where('building.sibermap_id', 77)
            ->where('rooms.0.floor_name', 'Lt 2 Gedung')
            ->where('rooms.0.sibermap_id', $building->rooms()->first()->sibermap_id));
});
