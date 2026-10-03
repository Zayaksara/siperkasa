<?php

namespace App\Jobs;

use App\Actions\Assets\ImportAssets;
use App\Models\AssetImport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class ImportAssetsJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public function __construct(public int $importId, public string $storagePath) {}

    public function handle(ImportAssets $importer): void
    {
        $import = AssetImport::findOrFail($this->importId);

        try {
            $importer->handle(Storage::disk('local')->path($this->storagePath), 'xlsx', $import);
        } finally {
            Storage::disk('local')->delete($this->storagePath);
        }
    }
}
