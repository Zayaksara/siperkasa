<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Throwable;

class BridgeInspectCommand extends Command
{
    protected $signature = 'bridge:inspect';

    protected $description = 'Tampilkan struktur (nama field + tipe, tanpa nilai) respons login & profil Bridge API.';

    public function handle(): int
    {
        if (! app()->environment('local')) {
            $this->error('Hanya untuk environment local.');

            return self::FAILURE;
        }

        $baseUrl = (string) config('services.bridge.base_url');

        if (trim($baseUrl) === '') {
            $this->error('BRIDGE_API_BASE_URL belum diisi.');

            return self::FAILURE;
        }

        $username = $this->ask('NIM/NIP');
        $password = $this->secret('Password portal');

        $client = Http::baseUrl(rtrim($baseUrl, '/'))
            ->acceptJson()
            ->timeout((int) config('services.bridge.timeout', 10));

        $login = $client->asForm()->post('/api/v2/portal/login', [
            'username' => $username,
            'password' => $password,
        ]);

        $this->line("Login: HTTP {$login->status()}");

        if (! $login->successful()) {
            $this->shape($login->json(), '');

            return self::FAILURE;
        }

        $token = $this->extractToken($login);

        if ($token === null) {
            $this->error('Token tidak ditemukan.');

            return self::FAILURE;
        }

        $this->line('== Struktur respons login ==');
        $this->shape($login->json(), '');

        try {
            $profil = $client->withToken($token)->get('/api/v2/profil');

            $this->line("Profil: HTTP {$profil->status()}");
            $this->line('== Struktur respons profil ==');
            $this->shape($profil->json(), '');
        } finally {
            $this->logout($client, $token);
        }

        return self::SUCCESS;
    }

    private function extractToken(Response $login): ?string
    {
        $body = $login->json();

        if (! is_array($body)) {
            return null;
        }

        $token = Arr::get($body, 'data.token') ?? Arr::get($body, 'token');

        return is_string($token) && $token !== '' ? $token : null;
    }

    private function logout(PendingRequest $client, string $token): void
    {
        try {
            $client->withToken($token)->post('/api/v2/logout');
        } catch (Throwable) {
            // Best-effort: gagal logout tidak mengubah hasil inspeksi.
        }
    }

    /**
     * Cetak struktur (path + tipe) saja. Nilai tidak pernah dicetak.
     */
    private function shape(mixed $value, string $path): void
    {
        $label = $path === '' ? 'body' : $path;

        if (is_array($value)) {
            if ($value === []) {
                $this->line("{$label}: array kosong");

                return;
            }

            if (array_is_list($value)) {
                $this->line("{$label}[] (list, ".count($value).' item)');
                $this->shape($value[0], $label.'[]');

                return;
            }

            foreach ($value as $key => $child) {
                if (strtolower((string) $key) === 'token') {
                    continue;
                }

                $this->shape($child, $path === '' ? (string) $key : "{$path}.{$key}");
            }

            return;
        }

        if (is_string($value)) {
            $this->line("{$label}: string".($value === '' ? ' (kosong)' : ''));

            return;
        }

        $type = match (true) {
            is_int($value) => 'int',
            is_float($value) => 'float',
            is_bool($value) => 'bool',
            is_null($value) => 'null',
            default => 'mixed',
        };

        $this->line("{$label}: {$type}");
    }
}
