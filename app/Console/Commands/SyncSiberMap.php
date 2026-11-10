<?php

namespace App\Console\Commands;

use App\Support\SiberMap\SiberMapException;
use App\Support\SiberMap\SiberMapSync;
use Illuminate\Console\Command;

class SyncSiberMap extends Command
{
    protected $signature = 'sibermap:sync {--prune-manual : Hapus gedung/ruangan manual yang tidak dipakai laporan}';

    protected $description = 'Sinkronkan gedung dan ruangan dari SiberMap V2';

    public function handle(SiberMapSync $sync): int
    {
        try {
            $r = $sync->run((bool) $this->option('prune-manual'));
        } catch (SiberMapException $e) {
            $this->error('Sinkronisasi SiberMap gagal: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Gedung: {$r->buildingsUpserted}, ruangan: {$r->roomsUpserted}, dihapus: {$r->removed}, dipertahankan (dipakai laporan): {$r->keptInUse}, manual dibersihkan: {$r->prunedManual}, dilewati: {$r->skipped}.");

        return self::SUCCESS;
    }
}
