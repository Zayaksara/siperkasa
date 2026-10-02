<?php

use App\Enums\UserRole;
use App\Models\User;

test('the factory builds users with the right role', function () {
    expect(User::factory()->create()->role)->toBe(UserRole::User);
    expect(User::factory()->reporter()->create()->role)->toBe(UserRole::User);
    expect(User::factory()->admin()->create()->role)->toBe(UserRole::Admin);
    expect(User::factory()->pic()->create()->role)->toBe(UserRole::Pic);
});

test('users are active by default and can be built inactive', function () {
    expect(User::factory()->create()->is_active)->toBeTrue();
    expect(User::factory()->inactive()->create()->is_active)->toBeFalse();
});

test('every role knows its dashboard route and label', function () {
    expect(UserRole::User->dashboardRoute())->toBe('user.dashboard');
    expect(UserRole::Admin->dashboardRoute())->toBe('admin.dashboard');
    expect(UserRole::Pic->dashboardRoute())->toBe('pic.dashboard');
    expect(UserRole::Pic->label())->toBe('PIC');
});

test('nim_nip keeps leading zeros', function () {
    $user = User::factory()->create(['nim_nip' => '0012345']);

    expect($user->fresh()->nim_nip)->toBe('0012345');
});
