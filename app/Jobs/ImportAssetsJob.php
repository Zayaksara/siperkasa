<?php

namespace App\Jobs;

use App\Actions\Assets\ImportAssets;
use App\Enums\AssetImportStatus;
use App\Models\AssetImport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ImportAssetsJob implements ShouldQueue
{
    use Queueable;

    /** Must stay below the queue connection's retry_after (config/queue.php). */
    public int $timeout = 900;

    public int $tries = 1;

    public function __construct(public int $importId, public string $storagePath) {}

    public function handle(ImportAssets $importer): void
    {
        try {
            $import = AssetImport::findOrFail($this->importId);

            $importer->handle(Storage::disk('local')->path($this->storagePath), 'xlsx', $import);
        } finally {
            Storage::disk('local')->delete($this->storagePath);
        }
    }

    public function failed(Throwable $e): void
    {
        $import = AssetImport::find($this->importId);

        if ($import && $import->status !== AssetImportStatus::Failed) {
            $import->update([
                'status' => AssetImportStatus::Failed,
                'errors' => [['line' => 0, 'message' => 'Impor gagal diproses. Coba unggah ulang.']],
            ]);
        }

        Storage::disk('local')->delete($this->storagePath);
    }
}
