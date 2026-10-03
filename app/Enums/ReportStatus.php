<?php

namespace App\Enums;

enum ReportStatus: string
{
    case Pending = 'Pending';
    case Assigned = 'Assigned';
    case InProgress = 'In_Progress';
    case OnHold = 'On_Hold';
    case Closed = 'Closed';
    case Rejected = 'Rejected';
    case Cancelled = 'Cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu',
            self::Assigned => 'Ditugaskan',
            self::InProgress => 'Dikerjakan',
            self::OnHold => 'Ditunda',
            self::Closed => 'Selesai',
            self::Rejected => 'Ditolak',
            self::Cancelled => 'Dibatalkan',
        };
    }

    /** @return list<self> */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Pending => [self::Assigned, self::Rejected, self::Cancelled],
            self::Assigned => [self::InProgress],
            self::InProgress => [self::OnHold, self::Closed],
            self::OnHold => [self::InProgress],
            self::Closed, self::Rejected, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedNext(), true);
    }

    public function isFinal(): bool
    {
        return $this->allowedNext() === [];
    }

    /** @return list<string> */
    public static function finalValues(): array
    {
        return array_values(array_map(
            fn (self $status) => $status->value,
            array_filter(self::cases(), fn (self $status) => $status->isFinal()),
        ));
    }
}
