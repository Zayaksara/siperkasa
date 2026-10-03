<?php

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;

beforeEach(fn () => $this->admin = User::factory()->admin()->create());

function usersCsv(array $rows): UploadedFile
{
    $lines = ['nim_nip,name,email,role,password'];
    foreach ($rows as $row) {
        $lines[] = implode(',', $row);
    }

    return UploadedFile::fake()->createWithContent('users.csv', implode("\n", $lines));
}

test('imports new users, updates existing ones and reports failures per row', function () {
    User::factory()->create(['nim_nip' => '1001', 'name' => 'Lama Satu', 'role' => UserRole::User]);
    User::factory()->create(['nim_nip' => '1002', 'name' => 'Lama Dua', 'role' => UserRole::User]);

    $file = usersCsv([
        ['1001', 'Baru Satu', '', 'user', ''],              // update
        ['1002', 'Baru Dua', '', 'pic', ''],                // update
        ['2001', 'Siti', 's1@example.com', 'user', 'abc12345'],
        ['2002', 'Budi', 's2@example.com', 'user', 'abc12345'],
        ['2003', 'Dewi', '', 'user', 'abc12345'],
        ['2004', 'Eko', '', 'pic', 'abc12345'],
        ['2005', 'Fani', '', 'user', 'abc12345'],
        ['2006', 'Gani', '', 'user', 'abc12345'],
        ['2007', 'Hadi', '', 'user', 'abc12345'],
        ['2008', 'Rusak', '', 'superman', 'abc12345'],      // bad role
    ]);

    $this->actingAs($this->admin)->post(route('admin.users.import'), ['file' => $file])->assertRedirect(route('admin.users.index'))
        ->assertSessionHas('import_result', fn ($r) => $r['created'] === 7 && $r['updated'] === 2 && $r['failed'] === 1
            && $r['errors'][0]['line'] === 11 && str_contains(strtolower($r['errors'][0]['message']), 'peran'));

    expect(User::where('nim_nip', '1001')->first()->name)->toBe('Baru Satu')
        ->and(User::where('nim_nip', '1002')->first()->role)->toBe(UserRole::Pic)
        ->and(User::where('nim_nip', '2008')->exists())->toBeFalse();
});

test('a blank password generates one that works and is shown once', function () {
    $file = usersCsv([['3001', 'Tanpa Password', '', 'user', '']]);

    $this->actingAs($this->admin)->post(route('admin.users.import'), ['file' => $file])
        ->assertSessionHas('import_result', function ($result) {
            $generated = $result['generated'][0] ?? null;

            return $generated !== null
                && $generated['nim_nip'] === '3001'
                && Hash::check($generated['password'], User::where('nim_nip', '3001')->value('password'));
        });
});

test('an email that belongs to another user fails that row only', function () {
    User::factory()->create(['email' => 'dipakai@example.com']);

    $file = usersCsv([
        ['4001', 'Bentrok', 'dipakai@example.com', 'user', 'abc12345'],
        ['4002', 'Aman', 'aman@example.com', 'user', 'abc12345'],
    ]);

    $this->actingAs($this->admin)->post(route('admin.users.import'), ['file' => $file])
        ->assertSessionHas('import_result', fn ($r) => $r['created'] === 1 && $r['failed'] === 1);

    expect(User::where('nim_nip', '4002')->exists())->toBeTrue();
});

test('re-importing never changes the active flag or the password of existing users', function () {
    $user = User::factory()->inactive()->create(['nim_nip' => '5001']);
    $hash = $user->password;

    $this->actingAs($this->admin)->post(route('admin.users.import'), ['file' => usersCsv([['5001', 'Nama Baru', '', 'user', 'passwordbaru1']])]);

    expect($user->fresh()->is_active)->toBeFalse()
        ->and($user->fresh()->password)->toBe($hash)
        ->and($user->fresh()->name)->toBe('Nama Baru');
});

test('only csv and xlsx files are accepted', function () {
    $this->actingAs($this->admin)->post(route('admin.users.import'), ['file' => UploadedFile::fake()->create('users.pdf', 10, 'application/pdf')])
        ->assertSessionHasErrors('file');
});

test('non-admins can not import users', function () {
    $this->actingAs(User::factory()->pic()->create())->post(route('admin.users.import'), [])->assertForbidden();
});

test('a row that would demote the importing admin fails while other rows still import', function () {
    $file = usersCsv([
        [$this->admin->nim_nip, 'Admin Sendiri', '', 'user', ''],
        ['7001', 'Orang Lain', '', 'user', 'abc12345'],
    ]);

    $this->actingAs($this->admin)->post(route('admin.users.import'), ['file' => $file])
        ->assertSessionHas('import_result', fn ($r) => $r['created'] === 1 && $r['failed'] === 1
            && $r['errors'][0]['line'] === 2 && $r['errors'][0]['message'] === 'Tidak bisa mengubah peran akun sendiri.');

    expect($this->admin->fresh()->role)->toBe(UserRole::Admin)
        ->and($this->admin->fresh()->name)->not->toBe('Admin Sendiri');
});

test('a file over 500 rows is rejected and nothing is written', function () {
    $rows = array_map(fn ($i) => ['9'.$i, 'User '.$i, '', 'user', 'abc12345'], range(1, 501));

    $this->actingAs($this->admin)->post(route('admin.users.import'), ['file' => usersCsv($rows)])
        ->assertSessionHasErrors(['file' => 'Maksimal 500 baris per file.']);

    expect(User::count())->toBe(1);
});

test('exactly 500 rows are accepted', function () {
    $rows = array_map(fn ($i) => ['8'.$i, 'User '.$i, '', 'user', 'abc12345'], range(1, 500));

    $this->actingAs($this->admin)->post(route('admin.users.import'), ['file' => usersCsv($rows)])
        ->assertSessionHas('import_result', fn ($r) => $r['created'] === 500);
});

test('demoting a pic through import clears the default pic of their categories', function () {
    $pic = User::factory()->pic()->create(['nim_nip' => '6001']);
    $category = Category::factory()->create(['default_pic_id' => $pic->id]);

    $this->actingAs($this->admin)->post(route('admin.users.import'), ['file' => usersCsv([['6001', $pic->name, '', 'user', '']])]);

    expect($category->fresh()->default_pic_id)->toBeNull();
});
