<?php

use App\Models\User;

test('profile page is displayed', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('profile.edit'))
        ->assertOk();
});

test('email can be updated and the name stays unchanged', function () {
    $user = User::factory()->create();
    $originalName = $user->name;

    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => 'Nama Palsu', 'email' => 'test@example.com'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->email)->toBe('test@example.com');
    expect($user->name)->toBe($originalName);
});

test('email is optional', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), ['email' => null])
        ->assertSessionHasNoErrors();

    expect($user->refresh()->email)->toBeNull();
});

test('nim_nip and role can not be changed from the profile', function () {
    $user = User::factory()->create(['nim_nip' => '111']);

    $this->actingAs($user)->patch(route('profile.update'), [
        'email' => $user->email,
        'nim_nip' => '999',
        'role' => 'admin',
    ]);

    expect($user->refresh()->nim_nip)->toBe('111');
    expect($user->role->value)->toBe('user');
});

test('there is no self-service account deletion', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->delete('/settings/profile', ['password' => 'password'])
        ->assertStatus(405);

    expect($user->fresh())->not->toBeNull();
});
