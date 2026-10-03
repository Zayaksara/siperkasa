<?php

use App\Models\Category;
use App\Models\Report;
use App\Models\ReportStatusLog;
use App\Models\User;
use App\Support\ReportPresenter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

beforeEach(fn () => $this->admin = User::factory()->admin()->create());

test('admin soft deletes a user with toast', function () {
    $user = User::factory()->create();

    $this->actingAs($this->admin)->delete(route('admin.users.destroy', $user))
        ->assertRedirect()
        ->assertInertiaFlash('toast.message', 'Akun dihapus.');

    expect(User::find($user->id))->toBeNull()
        ->and(User::withTrashed()->find($user->id)->trashed())->toBeTrue();
});

test('admin restores a trashed user', function () {
    $user = User::factory()->create();
    $user->delete();

    $this->actingAs($this->admin)->post(route('admin.users.restore', $user->id))
        ->assertRedirect()
        ->assertInertiaFlash('toast.message', 'Akun dipulihkan.');

    expect($user->fresh()->trashed())->toBeFalse();
});

test('admin cannot delete own account', function () {
    $this->actingAs($this->admin)->delete(route('admin.users.destroy', $this->admin))
        ->assertSessionHasErrors(['user' => 'Tidak bisa menghapus akun sendiri.']);

    expect(User::find($this->admin->id))->not->toBeNull();
});

test('super admin cannot be deleted', function () {
    $super = User::factory()->admin()->create(['nim_nip' => config('siperkasa.super_admin_nip')]);

    $this->actingAs($this->admin)->delete(route('admin.users.destroy', $super))
        ->assertSessionHasErrors(['user' => 'Super admin tidak bisa dihapus.']);

    expect(User::find($super->id))->not->toBeNull();
});

test('non admin gets 403 on delete and restore', function () {
    $target = User::factory()->create();
    $trashed = User::factory()->create();
    $trashed->delete();

    foreach ([User::factory()->create(), User::factory()->pic()->create()] as $actor) {
        $this->actingAs($actor)->delete(route('admin.users.destroy', $target))->assertForbidden();
        $this->actingAs($actor)->post(route('admin.users.restore', $trashed->id))->assertForbidden();
    }
});

test('trashed users are hidden by default and shown with the Terhapus filter', function () {
    User::factory()->create(['name' => 'Aktif']);
    User::factory()->create(['name' => 'Hilang'])->delete();

    $this->actingAs($this->admin)->get(route('admin.users.index'))
        ->assertInertia(fn ($page) => $page->has('users.data', 2));

    $this->actingAs($this->admin)->get(route('admin.users.index', ['status' => 'trashed']))
        ->assertInertia(fn ($page) => $page->has('users.data', 1)
            ->where('users.data.0.name', 'Hilang')
            ->where('users.data.0.deleted', true));
});

test('trashed user cannot log in', function () {
    $user = User::factory()->create(['nim_nip' => '777', 'password' => 'rahasia123']);
    $user->delete();

    $this->post('/login', ['nim_nip' => '777', 'password' => 'rahasia123'])->assertSessionHasErrors();

    $this->assertGuest();
});

test('deleting clears default pic and kills sessions', function () {
    $pic = User::factory()->pic()->create();
    $category = Category::factory()->create(['default_pic_id' => $pic->id]);
    DB::table('sessions')->insert(['id' => 'abc', 'user_id' => $pic->id, 'payload' => '', 'last_activity' => time()]);
    DB::table('sessions')->insert(['id' => 'def', 'user_id' => $this->admin->id, 'payload' => '', 'last_activity' => time()]);

    $this->actingAs($this->admin)->delete(route('admin.users.destroy', $pic));

    expect($category->fresh()->default_pic_id)->toBeNull()
        ->and(DB::table('sessions')->where('user_id', $pic->id)->count())->toBe(0)
        ->and(DB::table('sessions')->where('user_id', $this->admin->id)->count())->toBe(1);
});

test('category pic options exclude trashed users', function () {
    User::factory()->pic()->create(['name' => 'Pic Hilang'])->delete();
    User::factory()->pic()->create(['name' => 'Pic Ada']);

    $this->actingAs($this->admin)->get(route('admin.categories.index'))
        ->assertInertia(fn ($page) => $page->has('pics', 1)->where('pics.0.name', 'Pic Ada'));
});

test('reports of deleted people still present their names', function () {
    $pic = User::factory()->pic()->create(['name' => 'Pic Lama']);
    $report = Report::factory()->assigned($pic)->create();
    $reporter = $report->user;
    $admin = $report->admin;
    ReportStatusLog::create([
        'report_id' => $report->id, 'actor_id' => $pic->id,
        'from_status' => 'Pending', 'to_status' => 'Assigned',
    ]);

    foreach ([$reporter, $admin, $pic] as $person) {
        $person->delete();
    }

    $detail = ReportPresenter::detail(Report::find($report->id));

    expect($detail['reporter'])->toBe($reporter->name)
        ->and($detail['admin'])->toBe($admin->name)
        ->and($detail['pic'])->toBe('Pic Lama')
        ->and(collect($detail['timeline'])->pluck('actor')->filter()->all())->toContain('Pic Lama');
});

test('creating a user with a trashed NIM/NIP is refused', function () {
    User::factory()->create(['nim_nip' => '555'])->delete();

    $this->actingAs($this->admin)->post(route('admin.users.store'), [
        'nim_nip' => '555', 'name' => 'Baru', 'email' => null, 'role' => 'user', 'password' => 'rahasia123',
    ])->assertSessionHasErrors(['nim_nip' => 'NIM/NIP milik akun yang dihapus. Pulihkan akun tersebut.']);
});

test('updating a user to a trashed NIM/NIP is refused', function () {
    $user = User::factory()->create(['nim_nip' => '444']);
    $trashed = User::factory()->create(['nim_nip' => '555']);
    $trashed->delete();

    $response = $this->actingAs($this->admin)->put(route('admin.users.update', $user), [
        'nim_nip' => '555', 'name' => $user->name, 'email' => $user->email, 'role' => 'user',
    ]);

    $response->assertSessionHasErrors(['nim_nip' => 'NIM/NIP milik akun yang dihapus. Pulihkan akun tersebut.']);

    expect($user->fresh()->nim_nip)->toBe('444');
});

test('import refuses a trashed NIM/NIP row and continues', function () {
    $trashed = User::factory()->create(['nim_nip' => '555', 'name' => 'Lama']);
    $trashed->delete();

    $file = UploadedFile::fake()->createWithContent('users.csv', implode("\n", [
        'nim_nip,name,email,role,password',
        '555,Ganti,,user,abc12345',
        '556,Siti,,user,abc12345',
    ]));

    $this->actingAs($this->admin)->post(route('admin.users.import'), ['file' => $file])
        ->assertSessionHas('import_result', fn ($r) => $r['created'] === 1 && $r['failed'] === 1
            && $r['errors'][0]['message'] === 'NIM/NIP milik akun yang dihapus. Pulihkan akun tersebut.');

    expect(User::withTrashed()->where('nim_nip', '555')->first()->name)->toBe('Lama');
});

test('deleting a PIC with active reports is blocked', function () {
    $pic = User::factory()->pic()->create();
    Report::factory()->count(3)->assigned($pic)->create();
    Report::factory()->closed($pic)->create();

    $this->actingAs($this->admin)->delete(route('admin.users.destroy', $pic))
        ->assertSessionHasErrors(['user' => 'PIC masih menangani 3 laporan aktif. Alihkan laporan dulu.']);

    expect(User::find($pic->id))->not->toBeNull();
});

test('deactivating a PIC with active reports is blocked', function () {
    $pic = User::factory()->pic()->create();
    Report::factory()->inProgress($pic)->create();

    $this->actingAs($this->admin)->patch(route('admin.users.active', $pic), ['is_active' => false])
        ->assertSessionHasErrors(['user' => 'PIC masih menangani 1 laporan aktif. Alihkan laporan dulu.']);

    expect($pic->fresh()->is_active)->toBeTrue();
});

test('a PIC with only final reports can be deactivated and deleted', function () {
    $pic = User::factory()->pic()->create();
    Report::factory()->closed($pic)->create();

    $this->actingAs($this->admin)->patch(route('admin.users.active', $pic), ['is_active' => false])->assertSessionHasNoErrors();
    expect($pic->fresh()->is_active)->toBeFalse();

    $this->actingAs($this->admin)->delete(route('admin.users.destroy', $pic))->assertSessionHasNoErrors();
    expect(User::find($pic->id))->toBeNull();
});
