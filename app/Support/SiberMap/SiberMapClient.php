<?php

namespace App\Support\SiberMap;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Throwable;

class SiberMapClient
{
    /** @return list<array<string, mixed>> */
    public function buildings(): array
    {
        return $this->fetch('gedung');
    }

    /** @return list<array<string, mixed>> */
    public function rooms(): array
    {
        return $this->fetch('ruangan');
    }

    /** @return list<array<string, mixed>> */
    private function fetch(string $path): array
    {
        try {
            $json = $this->http()->get($path)->throw()->json();
        } catch (Throwable $e) {
            throw new SiberMapException("SiberMap /{$path} gagal: {$e->getMessage()}", 0, $e);
        }

        if (! is_array($json) || ($json['success'] ?? null) !== true || ! is_array($json['data'] ?? null)) {
            throw new SiberMapException("SiberMap /{$path} mengembalikan respons tidak valid.");
        }

        return array_values($json['data']);
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl((string) config('sibermap.base_url'))
            ->timeout((int) config('sibermap.timeout'))
            ->retry(2, 500, throw: false)
            ->acceptJson();
    }
}
