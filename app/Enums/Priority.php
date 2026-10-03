<?php

namespace App\Enums;

enum Priority: string
{
    case Low = 'Low';
    case Medium = 'Medium';
    case High = 'High';
    case Critical = 'Critical';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Rendah',
            self::Medium => 'Sedang',
            self::High => 'Tinggi',
            self::Critical => 'Kritis',
        };
    }

    /** Default SLA in days for this priority (config/siperkasa.php). */
    public function slaDays(): int
    {
        return (int) config('siperkasa.sla_days.'.$this->value);
    }
}
