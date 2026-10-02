<?php

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
