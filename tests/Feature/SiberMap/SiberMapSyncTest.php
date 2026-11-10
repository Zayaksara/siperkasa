<?php

use App\Models\Building;
use App\Models\Report;
use App\Models\Room;
use App\Support\SiberMap\SiberMapException;
use App\Support\SiberMap\SiberMapSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function sbBuilding(int $id, string $name, string $campus, int $locId = 1): array
{
    return ['id' => $id, 'location_id' => $locId, 'name' => $name, 'location' => ['id' => $locId, 'name' => $campus]];
}

function sbRoom(int $id, ?string $roomNumber, ?string $name, array $building, string $floorName = 'Lt 1 X'): array
{
    return [
        'id' => $id, 'floor_id' => 100 + $id, 'room_number' => $roomNumber, 'name' => $name,
        'floor' => ['id' => 100 + $id, 'building_id' => $building['id'], 'name' => $floorName, 'building' => $building],
    ];
}

function sbFake(array $buildings, array $rooms, int $status = 200, bool $success = true): void
{
    $base = config('sibermap.base_url');
    // Fresh factory each call: first matching stub wins, so re-faking would not override.
    Http::swap(new Factory);
    Http::preventStrayRequests();
    Http::fake([
        $base.'/gedung' => Http::response(['success' => $success, 'data' => $buildings], $status),
        $base.'/ruangan' => Http::response(['success' => $success, 'data' => $rooms], $status),
    ]);
}

beforeEach(fn () => Http::preventStrayRequests());

test('initial sync maps buildings and rooms', function () {
    $b1 = sbBuilding(1, ' Pasccasarjana ', 'Kampus 3');
    $b2 = sbBuilding(2, 'Rektorat', 'Kampus 1', 2);
    sbFake([$b1, $b2], [
        sbRoom(10, 'Dapur', null, $b1, 'Lt 1 Pascasarjana'),
        sbRoom(11, 'R-3', 'Aula', $b2, 'Lantai 3'),
        sbRoom(12, null, null, $b2, 'Basement'),
    ]);

    $result = app(SiberMapSync::class)->run();

    expect($result->buildingsUpserted)->toBe(2)->and($result->roomsUpserted)->toBe(3);
    $b = Building::where('sibermap_id', 1)->first();
    expect($b->code)->toBe('SM-1')->and($b->name)->toBe('Pasccasarjana')->and($b->campus_name)->toBe('Kampus 3')
        ->and($b->synced_at)->not->toBeNull()->and($b->isSynced())->toBeTrue();

    $dapur = Room::where('sibermap_id', 10)->first();
    expect($dapur->code)->toBe('SM-10')->and($dapur->name)->toBe('Dapur')->and($dapur->floor)->toBe(1)
        ->and($dapur->floor_name)->toBe('Lt 1 Pascasarjana')->and($dapur->building_id)->toBe($b->id);
    $aula = Room::where('sibermap_id', 11)->first();
    expect($aula->name)->toBe('Aula')->and($aula->floor)->toBe(3);
    $basement = Room::where('sibermap_id', 12)->first();
    expect($basement->name)->toBe('Ruang 12')->and($basement->floor)->toBeNull()->and($basement->floor_name)->toBe('Basement');
});

test('sync is idempotent and takes latest names', function () {
    $b = sbBuilding(1, 'Lama', 'Kampus 3');
    sbFake([$b], [sbRoom(10, 'A', null, $b)]);
    app(SiberMapSync::class)->run();

    $b['name'] = 'Baru';
    sbFake([$b], [sbRoom(10, 'A2', null, $b)]);
    app(SiberMapSync::class)->run();

    expect(Building::count())->toBe(1)->and(Room::count())->toBe(1)
        ->and(Building::first()->name)->toBe('Baru')->and(Room::first()->name)->toBe('A2');
});

test('building only in nested floor building is created', function () {
    $b = sbBuilding(5, 'Tersembunyi', 'Kampus 2', 2);
    sbFake([], [sbRoom(20, 'X', null, $b), ['id' => 21, 'room_number' => 'Y', 'name' => null, 'floor' => null]]);

    $result = app(SiberMapSync::class)->run();

    expect(Building::where('sibermap_id', 5)->first()->campus_name)->toBe('Kampus 2')
        ->and(Room::count())->toBe(1)->and($result->skipped)->toBe(1);
});

test('rooms missing from api are removed unless in use', function () {
    $b = sbBuilding(1, 'G', 'K');
    sbFake([$b], [sbRoom(10, 'A', null, $b), sbRoom(11, 'B', null, $b), sbRoom(12, 'C', null, $b)]);
    app(SiberMapSync::class)->run();
    Report::factory()->create(['room_id' => Room::where('sibermap_id', 11)->value('id')]);

    sbFake([$b], [sbRoom(10, 'A', null, $b)]);
    $result = app(SiberMapSync::class)->run();

    expect(Room::where('sibermap_id', 12)->exists())->toBeFalse()
        ->and(Room::where('sibermap_id', 11)->exists())->toBeTrue()
        ->and(Room::where('sibermap_id', 10)->exists())->toBeTrue()
        ->and($result->removed)->toBe(1)->and($result->keptInUse)->toBe(1);
});

test('api failure throws and leaves db untouched', function (int $status, bool $success) {
    $b = sbBuilding(1, 'G', 'K');
    sbFake([$b], [sbRoom(10, 'A', null, $b)]);
    app(SiberMapSync::class)->run();

    sbFake([], [], $status, $success);
    expect(fn () => app(SiberMapSync::class)->run())->toThrow(SiberMapException::class);
    expect(Building::count())->toBe(1)->and(Room::count())->toBe(1);
})->with([[500, true], [200, false]]);

test('manual rows are untouched without prune flag and pruned with it', function () {
    $b = sbBuilding(1, 'G', 'K');
    sbFake([$b], [sbRoom(10, 'A', null, $b)]);

    $manualEmpty = Building::factory()->create();
    $manualUsed = Building::factory()->create();
    $roomFree = Room::factory()->create(['building_id' => $manualUsed->id]);
    $roomUsed = Room::factory()->create(['building_id' => $manualUsed->id]);
    Report::factory()->create(['room_id' => $roomUsed->id]);

    $r = app(SiberMapSync::class)->run();
    expect($r->prunedManual)->toBe(0)->and(Building::count())->toBe(3)->and(Room::count())->toBe(3);

    $r = app(SiberMapSync::class)->run(pruneManual: true);
    expect($r->prunedManual)->toBe(2)
        ->and(Building::find($manualEmpty->id))->toBeNull()
        ->and(Room::find($roomFree->id))->toBeNull()
        ->and(Room::find($roomUsed->id))->not->toBeNull()
        ->and(Building::find($manualUsed->id))->not->toBeNull();
});

test('sibermap:sync command prints summary and fails cleanly', function () {
    $b = sbBuilding(1, 'G', 'K');
    sbFake([$b], [sbRoom(10, 'A', null, $b)]);
    $this->artisan('sibermap:sync')->expectsOutputToContain('Gedung: 1, ruangan: 1')->assertExitCode(0);

    sbFake([], [], 500);
    $this->artisan('sibermap:sync')->expectsOutputToContain('Sinkronisasi SiberMap gagal')->assertExitCode(1);
});
