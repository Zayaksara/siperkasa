<?php

namespace App\Actions\Assets;

use App\Enums\AssetImportStatus;
use App\Models\Asset;
use App\Models\AssetImport;
use App\Models\Category;
use App\Support\Bmn\BmnColumns;
use App\Support\Bmn\BmnValue;
use App\Support\Spreadsheet\RowReader;
use Closure;
use Throwable;

final class ImportAssets
{
    private const MAX_ERRORS = 100;

    private const UPDATE_COLUMNS = [
        'kode_barang', 'nup', 'name', 'brand', 'type', 'jenis_bmn', 'golongan', 'condition',
        'acquired_at', 'acquisition_value', 'kode_register', 'category_id', 'bmn_data', 'updated_at',
    ];

    /** @var array<string, int> category name => id */
    private array $categoryIds = [];

    public function __construct(private readonly int $batchSize = 1000) {}

    public function handle(string $path, string $extension = 'xlsx', ?AssetImport $import = null, ?Closure $onProgress = null): AssetImport
    {
        $import ??= AssetImport::create(['filename' => basename($path), 'status' => AssetImportStatus::Processing]);
        $import->update(['status' => AssetImportStatus::Processing, 'inserted' => 0, 'updated' => 0, 'failed' => 0, 'errors' => null]);

        $stats = ['inserted' => 0, 'updated' => 0, 'failed' => 0, 'errors' => []];
        $batch = [];
        $processed = 0;

        try {
            foreach (RowReader::assoc($path, $extension) as $line => $row) {
                $kodeBarang = BmnValue::string($row[BmnColumns::KODE_BARANG] ?? null);
                $nup = BmnValue::int($row[BmnColumns::NUP] ?? null);

                if ($kodeBarang === null || $nup === null) {
                    continue; // separator row
                }

                $processed++;
                $error = $this->validate($row);

                if ($error !== null) {
                    $stats['failed']++;

                    if (count($stats['errors']) < self::MAX_ERRORS) {
                        $stats['errors'][] = ['line' => $line, 'message' => $error];
                    }

                    continue;
                }

                $code = "{$kodeBarang}-{$nup}";
                $batch[$code] = $this->toAttributes($code, $kodeBarang, $nup, $row);

                if (count($batch) >= $this->batchSize) {
                    $this->flush($batch, $stats);

                    if ($onProgress) {
                        $onProgress($processed);
                    }
                }
            }

            $this->flush($batch, $stats);

            if ($onProgress) {
                $onProgress($processed);
            }
        } catch (Throwable $e) {
            $import->update([
                'status' => AssetImportStatus::Failed,
                'inserted' => $stats['inserted'],
                'updated' => $stats['updated'],
                'failed' => $stats['failed'],
                'errors' => [...array_slice($stats['errors'], 0, self::MAX_ERRORS - 1), ['line' => 0, 'message' => $e->getMessage()]],
            ]);

            throw $e;
        }

        $import->update([
            'status' => AssetImportStatus::Done,
            'inserted' => $stats['inserted'],
            'updated' => $stats['updated'],
            'failed' => $stats['failed'],
            'errors' => $stats['errors'] ?: null,
        ]);

        return $import;
    }

    /** @param array<string, mixed> $row */
    private function validate(array $row): ?string
    {
        if (BmnValue::string($row[BmnColumns::NAMA_BARANG] ?? null) === null) {
            return 'Nama Barang kosong.';
        }

        if (BmnValue::string($row[BmnColumns::JENIS_BMN] ?? null) === null) {
            return 'Jenis BMN kosong.';
        }

        if (! in_array(BmnValue::string($row[BmnColumns::GOLONGAN] ?? null), ['IT', 'NON-IT'], true)) {
            return 'Golongan Aset harus IT atau NON-IT.';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function toAttributes(string $code, string $kodeBarang, int $nup, array $row): array
    {
        $now = now();
        $kelompok = BmnValue::string($row[BmnColumns::KELOMPOK] ?? null);

        return [
            'asset_code' => $code,
            'kode_barang' => $kodeBarang,
            'nup' => $nup,
            'name' => BmnValue::string($row[BmnColumns::NAMA_BARANG]),
            'brand' => BmnValue::string($row[BmnColumns::MERK] ?? null),
            'type' => BmnValue::string($row[BmnColumns::TIPE] ?? null),
            'jenis_bmn' => BmnValue::string($row[BmnColumns::JENIS_BMN]),
            'golongan' => BmnValue::string($row[BmnColumns::GOLONGAN]),
            'condition' => BmnValue::string($row[BmnColumns::KONDISI] ?? null),
            'acquired_at' => BmnValue::date($row[BmnColumns::TGL_PEROLEHAN] ?? null),
            'acquisition_value' => BmnValue::decimal($row[BmnColumns::NILAI_PEROLEHAN] ?? null),
            'kode_register' => BmnValue::string($row[BmnColumns::KODE_REGISTER] ?? null),
            'category_id' => $kelompok === null ? null : $this->categoryId($kelompok),
            'bmn_data' => json_encode(
                array_map(fn ($value) => BmnValue::json($value), $row),
                JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE,
            ),
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    private function categoryId(string $name): int
    {
        return $this->categoryIds[$name] ??= Category::firstOrCreate(['name' => $name])->id;
    }

    /**
     * @param  array<string, array<string, mixed>>  $batch
     * @param  array{inserted: int, updated: int, failed: int, errors: list<array{line: int, message: string}>}  $stats
     */
    private function flush(array &$batch, array &$stats): void
    {
        if ($batch === []) {
            return;
        }

        $codes = array_keys($batch);
        $existing = Asset::whereIn('asset_code', $codes)->count();

        $inserted = count($codes) - $existing;

        Asset::upsert(array_values($batch), ['asset_code'], self::UPDATE_COLUMNS);

        $stats['updated'] += $existing;
        $stats['inserted'] += $inserted;

        $batch = [];
    }
}
