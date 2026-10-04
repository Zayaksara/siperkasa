<?php

namespace App\Actions\Reports;

use App\Enums\ReportStatus;
use DomainException;

class InvalidTransition extends DomainException
{
    public static function between(ReportStatus $from, ReportStatus $to): self
    {
        return new self("Status laporan tidak bisa berubah dari {$from->value} ke {$to->value}.");
    }

    public static function notAssigned(): self
    {
        return new self('Laporan tidak lagi ditugaskan kepada Anda.');
    }
}
