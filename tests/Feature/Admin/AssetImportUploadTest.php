<?php

use App\Enums\AssetImportStatus;
use App\Jobs\ImportAssetsJob;
use App\Models\Asset;
use App\Models\AssetImport;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BmnFixture;

const XLSX_MIME = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

test('admin uploads a file and an import job is queued', function () {
    Queue::fake();
    Storage::fake('local');
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.asset-imports.store'), [
            'file' => UploadedFile::fake()->create('aset.xlsx', 100, XLSX_MIME),
        ])
        ->assertRedirect(route('admin.asset-imports.index'));

    $import = AssetImport::firstOrFail();
    expect($import->status)->toBe(AssetImportStatus::Queued)
        ->and($import->user_id)->toBe($admin->id)
        ->and($import->filename)->toBe('aset.xlsx');

    Queue::assertPushed(ImportAssetsJob::class, fn ($job) => $job->importId === $import->id);
});

test('the queued job imports the file and records the result', function () {
    Storage::fake('local');
    $path = BmnFixture::xlsx([BmnFixture::row(), BmnFixture::row(['NUP' => 2])]);
    $upload = new UploadedFile($path, 'aset.xlsx', XLSX_MIME, null, true);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.asset-imports.store'), ['file' => $upload]);

    expect(Asset::count())->toBe(2);

    $import = AssetImport::firstOrFail();
    expect($import->status)->toBe(AssetImportStatus::Done)
        ->and($import->inserted)->toBe(2);
    expect(Storage::disk('local')->allFiles('imports'))->toBeEmpty();
});

test('only xlsx files up to 20 MB are accepted', function () {
    Queue::fake();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.asset-imports.store'), ['file' => UploadedFile::fake()->create('aset.pdf', 10, 'application/pdf')])
        ->assertSessionHasErrors('file');

    $this->actingAs($admin)
        ->post(route('admin.asset-imports.store'), ['file' => UploadedFile::fake()->create('aset.xlsx', 21000, XLSX_MIME)])
        ->assertSessionHasErrors('file');

    Queue::assertNothingPushed();
});

test('non-admins can not upload or list imports', function (string $state) {
    $user = User::factory()->{$state}()->create();

    $this->actingAs($user)->get(route('admin.asset-imports.index'))->assertForbidden();
    $this->actingAs($user)->post(route('admin.asset-imports.store'))->assertForbidden();
})->with(['reporter', 'pic']);

test('the import history lists the latest imports', function () {
    AssetImport::create(['filename' => 'a.xlsx', 'status' => AssetImportStatus::Done]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.asset-imports.index'))
        ->assertInertia(fn ($page) => $page
            ->component('admin/assets/Imports')
            ->has('imports', 1)
            ->where('imports.0.status', 'done'));
});
