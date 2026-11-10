<?php

namespace App\Support\SiberMap;

use App\Models\Building;
use App\Models\Room;
use Illuminate\Support\Facades\DB;

class SiberMapSync
{
    public function __construct(private readonly SiberMapClient $client) {}

    public function run(bool $pruneManual = false): SyncResult
    {
        // Fetch both first: any failure throws before the DB is touched.
        $apiBuildings = $this->client->buildings();
        $apiRooms = $this->client->rooms();

        return DB::transaction(fn () => $this->apply($apiBuildings, $apiRooms, $pruneManual));
    }

    /**
     * @param  list<array<string, mixed>>  $apiBuildings
     * @param  list<array<string, mixed>>  $apiRooms
     */
    private function apply(array $apiBuildings, array $apiRooms, bool $pruneManual): SyncResult
    {
        $now = now();
        $buildingIds = [];
        $roomIds = [];
        $localBuildings = []; // sibermap_id => local id
        $skipped = 0;

        $upsertBuilding = function (array $b) use ($now, &$buildingIds, &$localBuildings): void {
            $id = (int) $b['id'];
            if (isset($localBuildings[$id])) {
                return;
            }
            $campus = isset($b['location']['name']) ? trim((string) $b['location']['name']) : '';
            $building = Building::updateOrCreate(['sibermap_id' => $id], [
                'code' => "SM-{$id}",
                'name' => trim((string) ($b['name'] ?? '')),
                'campus_name' => $campus === '' ? null : $campus,
                'synced_at' => $now,
            ]);
            $buildingIds[] = $id;
            $localBuildings[$id] = $building->id;
        };

        foreach ($apiBuildings as $b) {
            if (isset($b['id'])) {
                $upsertBuilding($b);
            }
        }

        foreach ($apiRooms as $r) {
            $floor = $r['floor'] ?? null;
            $buildingSiberId = is_array($floor) ? ($floor['building_id'] ?? null) : null;
            if (! isset($r['id']) || $buildingSiberId === null) {
                $skipped++;

                continue;
            }

            $buildingSiberId = (int) $buildingSiberId;
            if (! isset($localBuildings[$buildingSiberId]) && is_array($floor['building'] ?? null) && isset($floor['building']['id'])) {
                $upsertBuilding($floor['building']);
            }
            if (! isset($localBuildings[$buildingSiberId])) {
                $skipped++;

                continue;
            }

            $id = (int) $r['id'];
            $floorName = trim((string) ($floor['name'] ?? ''));
            $floorNumber = preg_match('/(?:lt|lantai)\.?\s*(\d+)/i', $floorName, $m) ? (int) $m[1] : null;

            Room::updateOrCreate(['sibermap_id' => $id], [
                'building_id' => $localBuildings[$buildingSiberId],
                'code' => "SM-{$id}",
                'name' => $this->roomName($r, $id),
                'floor_name' => $floorName === '' ? null : $floorName,
                'floor' => $floorNumber,
                'synced_at' => $now,
            ]);
            $roomIds[] = $id;
        }

        $removed = 0;
        $kept = 0;

        foreach (Room::whereNotNull('sibermap_id')->whereNotIn('sibermap_id', $roomIds)->get() as $room) {
            if ($room->isInUse()) {
                $kept++;
            } else {
                $room->delete();
                $removed++;
            }
        }
        foreach (Building::whereNotNull('sibermap_id')->whereNotIn('sibermap_id', $buildingIds)->get() as $building) {
            if ($building->isInUse()) {
                $kept++;
            } else {
                $building->delete();
                $removed++;
            }
        }

        $pruned = $pruneManual ? $this->pruneManual() : 0;

        return new SyncResult(count($buildingIds), count($roomIds), $removed, $kept, $pruned, $skipped);
    }

    /** @param array<string, mixed> $r */
    private function roomName(array $r, int $id): string
    {
        foreach (['name', 'room_number'] as $key) {
            $value = trim((string) ($r[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return "Ruang {$id}";
    }

    private function pruneManual(): int
    {
        $pruned = 0;

        foreach (Room::whereNull('sibermap_id')->get() as $room) {
            if (! $room->isInUse()) {
                $room->delete();
                $pruned++;
            }
        }
        foreach (Building::whereNull('sibermap_id')->get() as $building) {
            if (! $building->isInUse()) {
                $building->delete();
                $pruned++;
            }
        }

        return $pruned;
    }
}
