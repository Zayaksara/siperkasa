<?php

namespace App\Actions\Reports;

/** The acting PIC is no longer the assigned PIC (lost the task to a reassignment). */
class NotAssigned extends InvalidTransition
{
    public static function make(): self
    {
        return new self('Laporan tidak lagi ditugaskan kepada Anda.');
    }
}
