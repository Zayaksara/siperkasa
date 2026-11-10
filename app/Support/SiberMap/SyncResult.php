<?php

namespace App\Support\SiberMap;

final readonly class SyncResult
{
    public function __construct(
        public int $buildingsUpserted = 0,
        public int $roomsUpserted = 0,
        public int $removed = 0,
        public int $keptInUse = 0,
        public int $prunedManual = 0,
        public int $skipped = 0,
    ) {}
}
