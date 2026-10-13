<?php

use Illuminate\Support\Facades\Route;

test('production web 404 renders the Inertia Error page', function () {
    $original = app()['env'];

    try {
        app()->detectEnvironment(fn () => 'production');

        $this->get('/tidak-ada-halaman')
            ->assertStatus(404)
            ->assertInertia(fn ($page) => $page->component('Error')->where('status', 404));
    } finally {
        app()->detectEnvironment(fn () => $original);
    }
});

test('production JSON 404 stays JSON', function () {
    $original = app()['env'];

    try {
        app()->detectEnvironment(fn () => 'production');

        $this->getJson('/tidak-ada-halaman')
            ->assertStatus(404)
            ->assertHeaderMissing('X-Inertia')
            ->assertJsonMissingPath('component');
    } finally {
        app()->detectEnvironment(fn () => $original);
    }
});

function asProduction(Closure $callback): void
{
    $original = app()['env'];

    try {
        app()->detectEnvironment(fn () => 'production');
        $callback();
    } finally {
        app()->detectEnvironment(fn () => $original);
    }
}

dataset('error statuses', [403, 419, 429, 500, 503]);

test('production web errors render the Inertia Error page', function (int $status) {
    Route::get("/_throwaway/{$status}", fn () => abort($status));

    asProduction(function () use ($status) {
        $this->get("/_throwaway/{$status}")
            ->assertStatus($status)
            ->assertInertia(fn ($page) => $page->component('Error')->where('status', $status));
    });
})->with('error statuses');

test('production JSON 419 and 429 stay JSON', function (int $status) {
    Route::get("/_throwaway/{$status}", fn () => abort($status));

    asProduction(function () use ($status) {
        $this->getJson("/_throwaway/{$status}")
            ->assertStatus($status)
            ->assertHeaderMissing('X-Inertia')
            ->assertJsonMissingPath('component');
    });
})->with([419, 429]);

test('error preview route is absent outside local', function () {
    asProduction(function () {
        $this->get('/_error/404')->assertNotFound();
        $this->get('/_error/500')->assertNotFound();
    });

    expect(Route::has('error.preview'))->toBeFalse();
});

test('503 blade fallback renders without Vite', function () {
    $html = view('errors.503')->render();

    expect($html)
        ->toContain('Sedang pemeliharaan')
        ->toContain('SiPerkasa sedang diperbarui dan akan segera kembali.')
        ->toContain('Butuh bantuan? Hubungi PUSTIKOM UIN Siber Syekh Nurjati Cirebon.')
        ->not->toContain('/build/');
});
