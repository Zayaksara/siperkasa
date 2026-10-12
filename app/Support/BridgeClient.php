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
     * @return array{username: string, name: string, email: ?string, portal_role: ?string, phone: ?string, faculty: ?string, study_program_code: ?string, study_program: ?string, degree_level: ?string, entry_year: ?int, profile_loaded: bool}|null null when credentials are rejected
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
            'name' => $this->firstFilled($profile, ['nama', 'nama_lengkap', 'name'])
                ?? $this->text($login->json('data.nama'), 255)
                ?? $username,
            'email' => $this->email($profile),
            'portal_role' => $this->text($login->json('data.role'), 50),
            'phone' => $this->phone($profile['no_hp'] ?? null),
            'faculty' => $this->text($profile['fakultas_nama'] ?? null, 255),
            'study_program_code' => $this->text($profile['program_studi_kode'] ?? null, 20),
            'study_program' => $this->text($profile['program_studi_nama'] ?? null, 255),
            'degree_level' => $this->text($profile['jenjang'] ?? null, 20),
            'entry_year' => $this->entryYear($profile['tahun_masuk'] ?? null),
            'profile_loaded' => $profile !== [],
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
            $value = $this->text($profile[$key] ?? null, 255);

            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $profile
     */
    private function email(array $profile): ?string
    {
        foreach (['email_kampus', 'email'] as $key) {
            $value = $profile[$key] ?? null;

            if (is_string($value) && mb_strlen($value) <= 255 && filter_var($value, FILTER_VALIDATE_EMAIL)) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Trimmed string, or null when blank or longer than the target column (avoids "data too long" on save).
     */
    private function text(mixed $value, int $max): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' || mb_strlen($value) > $max ? null : $value;
    }

    private function phone(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);
        $plus = str_starts_with($value, '+') ? '+' : '';
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        $phone = $plus.$digits;

        return strlen($digits) >= 8 && strlen($phone) <= 30 ? $phone : null;
    }

    private function entryYear(mixed $value): ?int
    {
        if (is_string($value) && ctype_digit(trim($value))) {
            $value = (int) trim($value);
        }

        return is_int($value) && $value >= 1950 && $value <= 2100 ? $value : null;
    }
}
