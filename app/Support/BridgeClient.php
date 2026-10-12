<?php

namespace App\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Client for the campus Bridge API v2 (eCampuz) login.
 *
 * Never stores or logs the token or password.
 */
final class BridgeClient
{
    public function enabled(): bool
    {
        return filled(config('services.bridge.base_url'));
    }

    /**
     * @return array{username: string, name: string, email: ?string}|null null when credentials are rejected
     *
     * @throws BridgeUnavailableException when the Bridge cannot be reached or errors (5xx)
     */
    public function authenticate(string $username, string $password): ?array
    {
        try {
            $login = $this->http()->asForm()->post('/api/v2/portal/login', [
                'username' => $username,
                'password' => $password,
            ]);
        } catch (ConnectionException $e) {
            throw new BridgeUnavailableException('Bridge API unreachable.', 0, $e);
        }

        if ($login->serverError()) {
            throw new BridgeUnavailableException('Bridge API server error.');
        }

        if (! $login->successful()) {
            return null;
        }

        $token = $login->json('data.token') ?? $login->json('token');

        if (! is_string($token) || $token === '') {
            return null;
        }

        $profile = $this->profile($token);

        $this->logout($token);

        return [
            'username' => $username,
            'name' => $this->firstFilled($profile, ['nama', 'nama_lengkap', 'name']) ?? $username,
            'email' => $this->email($profile),
        ];
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.bridge.base_url'), '/'))
            ->acceptJson()
            ->timeout((int) config('services.bridge.timeout'));
    }

    /**
     * Best-effort: credentials are already verified, so failures yield an empty profile.
     *
     * @return array<string, mixed>
     */
    private function profile(string $token): array
    {
        try {
            $response = $this->http()->withToken($token)->get('/api/v2/profil');

            if (! $response->successful()) {
                return [];
            }

            $data = $response->json('data') ?? $response->json();

            return is_array($data) ? $data : [];
        } catch (Throwable) {
            return [];
        }
    }

    private function logout(string $token): void
    {
        try {
            $this->http()->withToken($token)->post('/api/v2/logout');
        } catch (Throwable) {
            // Best-effort cleanup only.
        }
    }

    /**
     * @param  array<string, mixed>  $profile
     * @param  list<string>  $keys
     */
    private function firstFilled(array $profile, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (filled($profile[$key] ?? null) && is_string($profile[$key])) {
                return $profile[$key];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $profile
     */
    private function email(array $profile): ?string
    {
        foreach (['email', 'email_kampus'] as $key) {
            $value = $profile[$key] ?? null;

            if (is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL)) {
                return $value;
            }
        }

        return null;
    }
}
