<?php

namespace Tests\Support;

use App\Support\Bmn\BmnColumns;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

final class BmnFixture
{
    /** A realistic TIK asset row keyed by column name; override any column. */
    public static function row(array $overrides = []): array
    {
        return array_merge([
            'Jenis BMN' => 'MESIN PERALATAN KHUSUS TIK',
            'Kode Barang' => '3100101001',
            'NUP' => 1,
            'Nama Barang' => 'Mainframe (Komputer Jaringan)',
            'Merk' => 'IBM',
            'Kondisi' => 'Baik',
            'Tanggal Perolehan' => 39063,
            'Nilai Perolehan' => 1500000,
            'Kode Register' => 'REG-001',
            'Lokasi Ruang' => 'Belum berlokasi',
            'Golongan Aset' => 'IT',
            'Kelompok Aset' => 'Komputer & Server',
        ], $overrides);
    }

    /**
     * Writes an .xlsx shaped like the real export: header, one blank separator row
     * (only the two category columns filled), then the given rows.
     *
     * @param  array<int, array<string, mixed>>  $rows  column name => value (see row())
     * @return string absolute path of a temporary file
     */
    public static function xlsx(array $rows, bool $separator = true): string
    {
        $path = tempnam(sys_get_temp_dir(), 'bmn').'.xlsx';

        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(BmnColumns::ALL));

        if ($separator) {
            $writer->addRow(Row::fromValues(self::line([
                'Golongan Aset' => 'IT',
                'Kelompok Aset' => 'Lain-lain',
            ])));
        }

        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues(self::line($row)));
        }

        $writer->close();

        return $path;
    }

    private static function line(array $values): array
    {
        return array_map(fn (string $column) => $values[$column] ?? null, BmnColumns::ALL);
    }
}
