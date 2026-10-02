<?php

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Features;

test('login screen can be rendered', function () {
    $this->get(route('login'))->assertOk();
});

test('users authenticate with nim_nip and password', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'nim_nip' => $user->nim_nip,
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('surrounding spaces in the nim_nip are ignored', function () {
    $user = User::factory()->create(['nim_nip' => '198501012010011001']);

    $this->post(route('login.store'), [
        'nim_nip' => '  198501012010011001 ',
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($user);
});

test('a nim_nip with leading zeros only matches exactly', function () {
    $user = User::factory()->create(['nim_nip' => '0012345']);

    $this->post(route('login.store'), ['nim_nip' => '12345', 'password' => 'password']);
    $this->assertGuest();

    $this->post(route('login.store'), ['nim_nip' => '0012345', 'password' => 'password']);
    $this->assertAuthenticatedAs($user);
});

test('the email address can not be used to log in', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'nim_nip' => $user->email,
        'password' => 'password',
    ]);

    $this->assertGuest();
});

test('users can not authenticate with an invalid password', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'nim_nip' => $user->nim_nip,
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('nim_nip');

    $this->assertGuest();
});

test('an account without a password fails a password login cleanly', function () {
    $user = User::factory()->create(['password' => null]);

    $this->post(route('login.store'), [
        'nim_nip' => $user->nim_nip,
        'password' => 'anything',
    ])->assertSessionHasErrors('nim_nip');

    $this->assertGuest();
});

test('inactive accounts can not log in', function () {
    $user = User::factory()->inactive()->create();

    $this->post(route('login.store'), [
        'nim_nip' => $user->nim_nip,
        'password' => 'password',
    ])->assertSessionHasErrors(['nim_nip' => 'Akun Anda nonaktif. Hubungi admin.']);

    $this->assertGuest();
});

test('registration is closed', function () {
    $this->get('/register')->assertNotFound();
    $this->post('/register', [])->assertNotFound();
});

test('users with two factor enabled are redirected to the challenge', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    $user = User::factory()->withTwoFactor()->create();

    $this->post(route('login'), [
        'nim_nip' => $user->nim_nip,
        'password' => 'password',
    ])->assertRedirect(route('two-factor.login'));

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('logout'))->assertRedirect(route('home'));

    $this->assertGuest();
});

test('users are rate limited', function () {
    $user = User::factory()->create();

    RateLimiter::increment(md5('login'.implode('|', [$user->nim_nip, '127.0.0.1'])), amount: 5);

    $this->post(route('login.store'), [
        'nim_nip' => $user->nim_nip,
        'password' => 'wrong-password',
    ])->assertTooManyRequests();
});
