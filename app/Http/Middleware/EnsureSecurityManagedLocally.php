<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks password, two-factor and passkey management for portal accounts
 * whose credentials are managed by the campus portal.
 */
class EnsureSecurityManagedLocally
{
    private const PATHS = [
        'settings/password',
        'user/two-factor-*',
        'user/confirmed-two-factor-authentication',
        'user/passkeys',
        'user/passkeys/*',
        'passkeys/confirm',
        'passkeys/confirm/*',
    ];

    public function handle(Request $request, Closure $next, ?string $mode = null): Response
    {
        $user = $request->user();

        if ($mode === 'redirect') {
            return $user !== null && ! $user->managesSecurityLocally()
                ? redirect()->route('profile.edit')
                : $next($request);
        }

        if ($user !== null && ! $user->managesSecurityLocally() && $request->is(self::PATHS)) {
            abort(403, 'Keamanan akun dikelola portal kampus.');
        }

        return $next($request);
    }
}
