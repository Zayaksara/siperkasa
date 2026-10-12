<?php

use App\Enums\UserRole;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function portalUser(array $attrs = []): User
{
    return User::factory()->create(array_merge([
        'password' => null,
        'portal_synced_at' => now(),
        'portal_role' => 'Mahasiswa',
        'faculty' => 'Fakultas Contoh',
        'study_program' => 'Program Contoh',
        'degree_level' => 'S1',
        'entry_year' => 2022,
        'phone' => '081200000000',
    ], $attrs));
}

test('reporter cannot update profile', function () {
    $user = User::factory()->create(['email' => 'a@example.com']);

    $this->actingAs($user)
        ->patch(route('profile.update'), ['email' => 'b@example.com'])
        ->assertForbidden();

    expect($user->refresh()->email)->toBe('a@example.com');
});

test('pic cannot update profile', function () {
    $user = User::factory()->create(['role' => UserRole::Pic, 'email' => 'a@example.com']);

    $this->actingAs($user)
        ->patch(route('profile.update'), ['email' => 'b@example.com'])
        ->assertForbidden();
});

test('admin can update profile email', function () {
    $user = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($user)
        ->patch(route('profile.update'), ['email' => 'admin@example.com'])
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->email)->toBe('admin@example.com');
});

test('profile page exposes edit flag and portal profile', function () {
    $this->actingAs(portalUser())
        ->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Profile')
            ->where('canEditEmail', false)
            ->where('portalProfile.faculty', 'Fakultas Contoh')
            ->where('portalProfile.entry_year', 2022)
            ->where('portalProfile.phone', '081200000000')
            ->where('portalProfile.portal_role', 'Mahasiswa'),
        );
});

test('admin profile page can edit email and has no portal profile', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]))
        ->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('canEditEmail', true)
            ->where('portalProfile', null),
        );
});

test('non portal account has no portal profile', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Pic]))
        ->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('canEditEmail', false)
            ->where('portalProfile', null),
        );
});

test('security prop is false for portal accounts', function () {
    $this->actingAs(portalUser())
        ->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('canManageSecurity', false));
});

test('security prop is true for local accounts', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('canManageSecurity', true));
});

test('portal account with local password keeps security', function () {
    $user = portalUser(['password' => 'password']);

    expect($user->managesSecurityLocally())->toBeTrue();
    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('canManageSecurity', true));
});

test('portal account is redirected from security page', function () {
    $this->actingAs(portalUser())
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertRedirect(route('profile.edit'));
});

test('portal account cannot use password, 2fa or passkey routes', function () {
    $this->actingAs(portalUser())->withSession(['auth.password_confirmed_at' => time()]);

    $this->put(route('user-password.update'), [
        'password' => 'new-password-123', 'password_confirmation' => 'new-password-123',
    ])->assertForbidden();
    $this->post('/user/two-factor-authentication')->assertForbidden();
    $this->get('/user/two-factor-qr-code')->assertForbidden();
    $this->get('/user/passkeys/options')->assertForbidden();
    $this->post('/user/passkeys')->assertForbidden();
});

test('local accounts keep password update', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertSessionHasNoErrors();
});
