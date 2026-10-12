<?php

namespace App\Actions\Auth;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\BridgeClient;
use App\Support\BridgeUnavailableException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthenticateUser
{
    private const MEMO_KEY = 'siperkasa.authenticated_user';

    public function __construct(private readonly BridgeClient $bridge) {}

    /**
     * Local password first; role-user accounts without a match fall back to the campus Bridge API.
     */
    public function handle(Request $request): ?User
    {
        // Fortify invokes the callback twice per login (2FA check + attempt); resolve once per request.
        if ($request->attributes->has(self::MEMO_KEY)) {
            return $request->attributes->get(self::MEMO_KEY);
        }

        $user = $this->resolve($request);
        $request->attributes->set(self::MEMO_KEY, $user);

        return $user;
    }

    private function resolve(Request $request): ?User
    {
        $nimNip = trim((string) $request->input('nim_nip'));
        $password = (string) $request->input('password');

        if ($nimNip === '' || $password === '') {
            return null;
        }

        $user = User::withTrashed()->where('nim_nip', $nimNip)->first();

        // filled() guards passkey/Bridge-only accounts: Hash::check() throws on an empty hash.
        if ($user && ! $user->trashed() && filled($user->password) && Hash::check($password, $user->password)) {
            $this->ensureActive($user);

            return $user;
        }

        // Admin and PIC authenticate locally only.
        if ($user && $user->hasRole(UserRole::Admin, UserRole::Pic)) {
            return null;
        }

        if (! $this->bridge->enabled()) {
            return null;
        }

        try {
            $profile = $this->bridge->authenticate($nimNip, $password);
        } catch (BridgeUnavailableException) {
            throw ValidationException::withMessages([
                'nim_nip' => 'Login portal kampus sedang tidak dapat dihubungi. Coba lagi beberapa saat.',
            ]);
        }

        if ($profile === null) {
            return null;
        }

        if ($user?->trashed()) {
            $this->inactive();
        }

        $user = $user ? $this->syncExisting($user, $profile) : $this->createFromBridge($nimNip, $profile);

        $this->ensureActive($user);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $profile
     */
    private function createFromBridge(string $nimNip, array $profile): User
    {
        $user = new User([
            'nim_nip' => $nimNip,
            'name' => $profile['name'],
            'email' => $this->freeEmail($profile['email']),
            'role' => UserRole::User,
            'is_active' => true,
            'password' => null,
        ]);
        $user->fill($this->portalAttributes($profile));
        $user->portal_synced_at = now();
        $user->save();

        return $user;
    }

    /**
     * @param  array<string, mixed>  $profile
     */
    private function syncExisting(User $user, array $profile): User
    {
        // name === username means the profile was unavailable and name is only the fallback.
        if ($profile['name'] !== $profile['username']) {
            $user->name = $profile['name'];
        }

        // Portal is the email source; keep the current one when the portal's is taken or missing.
        $email = $this->freeEmail($profile['email'], $user);
        if ($email !== null) {
            $user->email = $email;
        }

        $user->fill($this->portalAttributes($profile));
        $user->portal_synced_at = now();
        $user->save();

        return $user;
    }

    /**
     * Portal-owned columns; when the profile endpoint failed, nothing is overwritten (no nulling).
     *
     * @param  array<string, mixed>  $profile
     * @return array<string, mixed>
     */
    private function portalAttributes(array $profile): array
    {
        $attributes = array_filter(['portal_role' => $profile['portal_role']], fn ($value) => $value !== null);

        if ($profile['profile_loaded']) {
            foreach (['phone', 'faculty', 'study_program_code', 'study_program', 'degree_level', 'entry_year'] as $key) {
                $attributes[$key] = $profile[$key];
            }
        }

        return $attributes;
    }

    private function freeEmail(?string $email, ?User $except = null): ?string
    {
        if (blank($email)) {
            return null;
        }

        $taken = User::withTrashed()
            ->where('email', $email)
            ->when($except, fn ($q) => $q->whereKeyNot($except->getKey()))
            ->exists();

        return $taken ? null : $email;
    }

    private function ensureActive(User $user): void
    {
        if (! $user->is_active) {
            $this->inactive();
        }
    }

    private function inactive(): never
    {
        throw ValidationException::withMessages([
            'nim_nip' => 'Akun Anda nonaktif. Hubungi admin.',
        ]);
    }
}
