<?php

namespace App\Support\Spreadsheet;

use Generator;
use InvalidArgumentException;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

final class RowReader
{
    /**
     * Streams the first sheet row by row (low memory).
     *
     * @return Generator<int, array<int, mixed>> 1-based row number => cell values
     */
    public static function rows(string $path, string $extension): Generator
    {
        $reader = match (strtolower($extension)) {
            'xlsx' => new XlsxReader,
            'csv', 'txt' => new CsvReader,
            default => throw new InvalidArgumentException("Jenis berkas tidak didukung: {$extension}"),
        };

        $reader->open($path);

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $line = 0;

                foreach ($sheet->getRowIterator() as $row) {
                    yield ++$line => $row->toArray();
                }

                break;
            }
        } finally {
            $reader->close();
        }
    }

    /**
     * Same as rows(), but the first row is the header and every following row is keyed by it.
     *
     * @return Generator<int, array<string, mixed>>
     */
    public static function assoc(string $path, string $extension): Generator
    {
        $header = null;

        foreach (self::rows($path, $extension) as $line => $cells) {
            if ($header === null) {
                $header = array_map(fn ($name) => trim((string) $name), $cells);

                continue;
            }

            $row = [];

            foreach ($header as $index => $name) {
                if ($name !== '') {
                    $row[$name] = $cells[$index] ?? null;
                }
            }

            yield $line => $row;
        }
    }
}
