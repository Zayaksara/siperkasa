<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

final class ReportSubmissionLimit
{
    public const MAX = 10;

    public const DECAY_SECONDS = 3600;

    public static function key(User $user): string
    {
        return 'reports:'.$user->id;
    }

    public static function tooMany(User $user): bool
    {
        return RateLimiter::tooManyAttempts(self::key($user), self::MAX);
    }

    public static function hit(User $user): void
    {
        RateLimiter::hit(self::key($user), self::DECAY_SECONDS);
    }
}
