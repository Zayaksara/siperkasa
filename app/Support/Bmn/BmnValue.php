<?php

namespace App\Support\Bmn;

use Carbon\CarbonImmutable;
use DateTimeInterface;

final class BmnValue
{
    /** Excel serial for 9999-12-31, used by SIMAK/SAKTI as "no date". */
    private const EMPTY_DATE_SERIAL = 2958465;

    public static function string(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_float($value) && floor($value) === $value) {
            $value = (int) $value;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    public static function int(mixed $value): ?int
    {
        $text = self::string($value);

        return $text !== null && is_numeric($text) ? (int) $text : null;
    }

    public static function decimal(mixed $value): ?string
    {
        $text = self::string($value);

        return $text !== null && is_numeric($text) ? number_format((float) $text, 2, '.', '') : null;
    }

    /** @return string|null Y-m-d, or null for blanks, invalid values and the 9999 placeholder */
    public static function date(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return (int) $value->format('Y') >= 9999 ? null : $value->format('Y-m-d');
        }

        if (is_numeric($value)) {
            $serial = (int) $value;

            if ($serial <= 0 || $serial >= self::EMPTY_DATE_SERIAL) {
                return null;
            }

            return CarbonImmutable::create(1899, 12, 30)->addDays($serial)->format('Y-m-d');
        }

        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}/', trim($value)) === 1) {
            return CarbonImmutable::parse(trim($value))->format('Y-m-d');
        }

        return null;
    }

    /** Normalises a raw cell for storage inside the bmn_data JSON column. */
    public static function json(mixed $value): mixed
    {
        if ($value instanceof DateTimeInterface) {
            return self::date($value);
        }

        if (is_float($value) && floor($value) === $value) {
            return (int) $value;
        }

        if (is_string($value)) {
            $text = trim($value);

            return $text === '' ? null : $text;
        }

        return $value;
    }
}
