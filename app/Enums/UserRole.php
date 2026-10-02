<?php

namespace App\Enums;

enum UserRole: string
{
    case User = 'user';
    case Admin = 'admin';
    case Pic = 'pic';

    public function label(): string
    {
        return match ($this) {
            self::User => 'Pelapor',
            self::Admin => 'Admin',
            self::Pic => 'PIC',
        };
    }

    /** Named route of the dashboard that belongs to this role. */
    public function dashboardRoute(): string
    {
        return match ($this) {
            self::User => 'user.dashboard',
            self::Admin => 'admin.dashboard',
            self::Pic => 'pic.dashboard',
        };
    }
}
