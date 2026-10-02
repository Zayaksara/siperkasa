<?php

use App\Models\User;

it('lets each role into its own area only', function (string $state, string $routeName, int $expected) {
    $user = User::factory()->{$state}()->create();

    $this->actingAs($user)->get(route($routeName))->assertStatus($expected);
})->with([
    ['reporter', 'user.dashboard', 200],
    ['reporter', 'admin.dashboard', 403],
    ['reporter', 'pic.dashboard', 403],
    ['admin', 'admin.dashboard', 200],
    ['admin', 'user.dashboard', 403],
    ['admin', 'pic.dashboard', 403],
    ['pic', 'pic.dashboard', 200],
    ['pic', 'user.dashboard', 403],
    ['pic', 'admin.dashboard', 403],
]);

it('sends guests to the login page for every role area', function (string $routeName) {
    $this->get(route($routeName))->assertRedirect(route('login'));
})->with(['user.dashboard', 'admin.dashboard', 'pic.dashboard', 'dashboard']);

it('redirects /dashboard to the dashboard of the role', function (string $state, string $routeName) {
    $user = User::factory()->{$state}()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route($routeName));
})->with([
    ['reporter', 'user.dashboard'],
    ['admin', 'admin.dashboard'],
    ['pic', 'pic.dashboard'],
]);

it('exposes the role to the frontend', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.dashboard'))
        ->assertInertia(fn ($page) => $page
            ->component('admin/Dashboard')
            ->where('auth.user.role', 'admin'));
});

it('logs out an account that is deactivated mid-session', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $user->update(['is_active' => false]);

    $this->get(route('user.dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});
