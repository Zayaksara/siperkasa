<?php

use App\Models\Building;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    Http::swap(new Factory);
    Http::preventStrayRequests();
});

function fakeSiberMapApi(int $status = 200, bool $success = true): void
{
    $base = config('sibermap.base_url');
    Http::fake([
        $base.'/gedung' => Http::response([
            'success' => $success,
            'data' => [['id' => 1, 'location_id' => 1, 'name' => 'Rektorat', 'location' => ['id' => 1, 'name' => 'Kampus 1']]],
        ], $status),
        $base.'/ruangan' => Http::response([
            'success' => $success,
            'data' => [[
                'id' => 10, 'floor_id' => 100, 'room_number' => 'R-1', 'name' => 'Aula',
                'floor' => ['id' => 100, 'building_id' => 1, 'name' => 'Lt 1', 'building' => ['id' => 1, 'name' => 'Rektorat', 'location' => ['id' => 1, 'name' => 'Kampus 1']]],
            ]],
        ], $status),
    ]);
}

test('admin runs sync and gets a success toast with counts', function () {
    fakeSiberMapApi();

    $this->actingAs($this->admin)->post(route('admin.sibermap.sync'))
        ->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Sinkron SiberMap selesai: 1 gedung, 1 ruang.']);

    expect(Building::where('sibermap_id', 1)->exists())->toBeTrue()
        ->and(Room::where('sibermap_id', 10)->exists())->toBeTrue();
});

test('failed sync shows an error toast and leaves the database untouched', function () {
    $manual = Building::factory()->create(['name' => 'Gedung Manual']);
    fakeSiberMapApi(status: 500);

    $this->actingAs($this->admin)->post(route('admin.sibermap.sync'))
        ->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'error', 'message' => 'Gagal menghubungi SiberMap. Coba lagi nanti.']);

    expect(Building::count())->toBe(1)
        ->and(Building::first()->id)->toBe($manual->id)
        ->and(Room::count())->toBe(0);
});

test('non-admins can not run sync', function () {
    fakeSiberMapApi();

    $this->actingAs(User::factory()->create())->post(route('admin.sibermap.sync'))->assertForbidden();

    Http::assertNothingSent();
});
