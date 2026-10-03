<?php

namespace App\Console\Commands;

use App\Actions\Assets\ImportAssets;
use Illuminate\Console\Command;

class ImportAssetsCommand extends Command
{
    protected $signature = 'assets:import {path : Path to the BMN .xlsx file}';

    protected $description = 'Import or update BMN assets from an Excel file';

    public function handle(ImportAssets $importer): int
    {
        $path = (string) $this->argument('path');

        if (! is_file($path)) {
            $this->error("Berkas tidak ditemukan: {$path}");

            return self::FAILURE;
        }

        $this->info('Mengimpor aset…');

        $import = $importer->handle(
            $path,
            pathinfo($path, PATHINFO_EXTENSION) ?: 'xlsx',
            null,
            fn (int $rows) => $this->output->write("\r{$rows} baris diproses"),
        );

        $this->newLine(2);
        $this->table(['Ditambah', 'Diperbarui', 'Gagal'], [[$import->inserted, $import->updated, $import->failed]]);

        foreach (array_slice($import->errors ?? [], 0, 10) as $error) {
            $this->warn("Baris {$error['line']}: {$error['message']}");
        }

        return self::SUCCESS;
    }
}
