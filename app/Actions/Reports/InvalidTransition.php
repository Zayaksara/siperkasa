<?php

namespace App\Actions\Reports;

use App\Enums\ReportStatus;
use DomainException;

class InvalidTransition extends DomainException
{
    /** The status already moved to $current, so the requested action no longer applies. */
    public static function changed(ReportStatus $current): self
    {
        return new self("Status laporan sudah berubah menjadi {$current->label()}. Muat ulang halaman.");
    }

    public static function notAssigned(): NotAssigned
    {
        return NotAssigned::make();
    }
}
