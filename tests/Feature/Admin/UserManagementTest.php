<?php

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(fn () => $this->admin = User::factory()->admin()->create());

test('admin lists users with search and filters', function () {
    User::factory()->create(['name' => 'Budi Santoso', 'nim_nip' => '2010001']);
    User::factory()->pic()->create(['name' => 'Pak Teknisi']);
    User::factory()->inactive()->create(['name' => 'Mantan Staf']);

    $this->actingAs($this->admin)->get(route('admin.users.index'))
        ->assertInertia(fn ($page) => $page->component('admin/users/Index')->has('users.data', 4));

    $this->actingAs($this->admin)->get(route('admin.users.index', ['q' => 'budi']))
        ->assertInertia(fn ($page) => $page->has('users.data', 1));

    $this->actingAs($this->admin)->get(route('admin.users.index', ['q' => '2010001']))
        ->assertInertia(fn ($page) => $page->has('users.data', 1));

    $this->actingAs($this->admin)->get(route('admin.users.index', ['role' => 'pic']))
        ->assertInertia(fn ($page) => $page->has('users.data', 1));

    $this->actingAs($this->admin)->get(route('admin.users.index', ['status' => 'inactive']))
        ->assertInertia(fn ($page) => $page->has('users.data', 1));
});

test('admin creates a user with a hashed password', function () {
    $this->actingAs($this->admin)->post(route('admin.users.store'), [
        'nim_nip' => '0012345', 'name' => 'Siti', 'email' => null, 'role' => 'user', 'password' => 'rahasia123',
    ])->assertRedirect();

    $user = User::where('nim_nip', '0012345')->firstOrFail();
    expect($user->role)->toBe(UserRole::User)
        ->and($user->is_active)->toBeTrue()
        ->and(Hash::check('rahasia123', $user->password))->toBeTrue();
});

test('a duplicate NIM/NIP is rejected', function () {
    User::factory()->create(['nim_nip' => '2010001']);

    $this->actingAs($this->admin)->post(route('admin.users.store'), [
        'nim_nip' => '2010001', 'name' => 'Lain', 'role' => 'user', 'password' => 'rahasia123',
    ])->assertSessionHasErrors(['nim_nip' => 'NIM/NIP sudah terdaftar.']);
});

test('admin updates name, email and role without touching the password', function () {
    $user = User::factory()->create();
    $hash = $user->password;

    $this->actingAs($this->admin)->put(route('admin.users.update', $user), [
        'nim_nip' => $user->nim_nip, 'name' => 'Nama Baru', 'email' => 'baru@example.com', 'role' => 'pic',
    ])->assertSessionHasNoErrors();

    expect($user->fresh()->name)->toBe('Nama Baru')
        ->and($user->fresh()->role)->toBe(UserRole::Pic)
        ->and($user->fresh()->password)->toBe($hash);
});

test('admin deactivates and reactivates a user', function () {
    $user = User::factory()->create();

    $this->actingAs($this->admin)->patch(route('admin.users.active', $user), ['is_active' => false]);
    expect($user->fresh()->is_active)->toBeFalse();

    $this->actingAs($this->admin)->patch(route('admin.users.active', $user), ['is_active' => true]);
    expect($user->fresh()->is_active)->toBeTrue();
});

test('admin resets a password', function () {
    $user = User::factory()->create();

    $this->actingAs($this->admin)->put(route('admin.users.password', $user), ['password' => 'passwordbaru1'])
        ->assertSessionHasNoErrors();

    expect(Hash::check('passwordbaru1', $user->fresh()->password))->toBeTrue();
});

test('an admin can not deactivate or demote their own account', function () {
    $this->actingAs($this->admin)->patch(route('admin.users.active', $this->admin), ['is_active' => false])
        ->assertSessionHasErrors('user');

    $this->actingAs($this->admin)->put(route('admin.users.update', $this->admin), [
        'nim_nip' => $this->admin->nim_nip, 'name' => $this->admin->name, 'role' => 'user',
    ])->assertSessionHasErrors('role');

    expect($this->admin->fresh()->is_active)->toBeTrue()
        ->and($this->admin->fresh()->role)->toBe(UserRole::Admin);
});

test('non-admins are forbidden', function (string $state) {
    $user = User::factory()->{$state}()->create();

    $this->actingAs($user)->get(route('admin.users.index'))->assertForbidden();
    $this->actingAs($user)->post(route('admin.users.store'), [])->assertForbidden();
})->with(['reporter', 'pic']);

test('demoting a pic clears the default pic of their categories', function () {
    $pic = User::factory()->pic()->create();
    $category = Category::factory()->create(['default_pic_id' => $pic->id]);

    $this->actingAs($this->admin)->put(route('admin.users.update', $pic), [
        'nim_nip' => $pic->nim_nip, 'name' => $pic->name, 'email' => $pic->email, 'role' => 'user',
    ])->assertSessionHasNoErrors();

    expect($category->fresh()->default_pic_id)->toBeNull();
});

test('deactivating a pic clears the default pic of their categories', function () {
    $pic = User::factory()->pic()->create();
    $category = Category::factory()->create(['default_pic_id' => $pic->id]);
    $other = Category::factory()->create(['default_pic_id' => User::factory()->pic()->create()->id]);

    $this->actingAs($this->admin)->patch(route('admin.users.active', $pic), ['is_active' => false]);

    expect($category->fresh()->default_pic_id)->toBeNull()
        ->and($other->fresh()->default_pic_id)->not->toBeNull();
});
