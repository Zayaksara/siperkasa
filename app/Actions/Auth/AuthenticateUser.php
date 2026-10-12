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
    public function __construct(private readonly BridgeClient $bridge) {}

    /**
     * Local password first; role-user accounts without a match fall back to the campus Bridge API.
     */
    public function handle(Request $request): ?User
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
     * @param  array{username: string, name: string, email: ?string}  $profile
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
        $user->save();

        return $user;
    }

    /**
     * @param  array{username: string, name: string, email: ?string}  $profile
     */
    private function syncExisting(User $user, array $profile): User
    {
        // name === username means the profile was unavailable and name is only the fallback.
        if ($profile['name'] !== $profile['username']) {
            $user->name = $profile['name'];
        }

        if (blank($user->email)) {
            $user->email = $this->freeEmail($profile['email'], $user);
        }

        if ($user->isDirty()) {
            $user->save();
        }

        return $user;
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
