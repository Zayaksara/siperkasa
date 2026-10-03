<?php

use App\Actions\Assets\ImportAssets;
use App\Enums\AssetImportStatus;
use App\Models\Asset;
use App\Models\Category;
use Tests\Support\BmnFixture;

afterEach(function () {
    foreach (glob(sys_get_temp_dir().'/bmn*.xlsx') ?: [] as $file) {
        @unlink($file);
    }
});

it('imports assets, skips the separator row and creates categories', function () {
    $path = BmnFixture::xlsx([
        BmnFixture::row(),
        BmnFixture::row(['Kode Barang' => '3100101001', 'NUP' => 2, 'Nama Barang' => 'Server', 'Kelompok Aset' => 'Komputer & Server']),
        BmnFixture::row(['Kode Barang' => '3020201002', 'NUP' => 1, 'Nama Barang' => 'Gerobak Dorong', 'Jenis BMN' => 'MESIN PERALATAN NON TIK', 'Golongan Aset' => 'NON-IT', 'Kelompok Aset' => 'Mesin & Perkakas']),
    ]);

    $import = app(ImportAssets::class)->handle($path);

    expect($import->status)->toBe(AssetImportStatus::Done)
        ->and($import->inserted)->toBe(3)
        ->and($import->updated)->toBe(0)
        ->and($import->failed)->toBe(0);

    expect(Asset::count())->toBe(3);
    expect(Category::pluck('name')->sort()->values()->all())
        ->toBe(['Komputer & Server', 'Mesin & Perkakas']);

    $asset = Asset::where('asset_code', '3100101001-1')->firstOrFail();
    expect($asset->name)->toBe('Mainframe (Komputer Jaringan)')
        ->and($asset->acquired_at->toDateString())->toBe('2006-12-12')
        ->and($asset->acquisition_value)->toBe('1500000.00')
        ->and($asset->golongan)->toBe('IT')
        ->and($asset->bmn_data['Merk'])->toBe('IBM')
        ->and($asset->bmn_data['Lokasi Ruang'])->toBe('Belum berlokasi');
});

it('treats the 9999-12-31 placeholder as an empty date', function () {
    $path = BmnFixture::xlsx([BmnFixture::row(['Tanggal Perolehan' => 2958465])]);

    app(ImportAssets::class)->handle($path);

    expect(Asset::firstOrFail()->acquired_at)->toBeNull();
});

it('is idempotent: a second import updates instead of duplicating', function () {
    $path = BmnFixture::xlsx([BmnFixture::row(), BmnFixture::row(['NUP' => 2])]);

    app(ImportAssets::class)->handle($path);
    $second = app(ImportAssets::class)->handle($path);

    expect(Asset::count())->toBe(2)
        ->and($second->inserted)->toBe(0)
        ->and($second->updated)->toBe(2);
});

it('updates changed fields when the same asset is imported again', function () {
    app(ImportAssets::class)->handle(BmnFixture::xlsx([BmnFixture::row(['Merk' => 'IBM'])]));
    app(ImportAssets::class)->handle(BmnFixture::xlsx([BmnFixture::row(['Merk' => 'Dell'])]));

    expect(Asset::count())->toBe(1);
    expect(Asset::firstOrFail()->brand)->toBe('Dell');
});

it('imports a row without a Kelompok Aset with no category', function () {
    $path = BmnFixture::xlsx([BmnFixture::row(['Kelompok Aset' => null])]);

    $import = app(ImportAssets::class)->handle($path);

    expect($import->failed)->toBe(0);
    expect(Asset::firstOrFail()->category_id)->toBeNull();
});

it('reports bad rows as failed and still imports the good ones', function () {
    $path = BmnFixture::xlsx([
        BmnFixture::row(),
        BmnFixture::row(['NUP' => 2, 'Golongan Aset' => 'LAINNYA']),
        BmnFixture::row(['NUP' => 3, 'Nama Barang' => null]),
    ]);

    $import = app(ImportAssets::class)->handle($path);

    expect($import->inserted)->toBe(1)
        ->and($import->failed)->toBe(2)
        ->and($import->errors)->toHaveCount(2)
        ->and($import->errors[0])->toHaveKeys(['line', 'message']);
    expect(Asset::count())->toBe(1);
});

it('handles numeric Kode Barang cells without scientific notation', function () {
    $path = BmnFixture::xlsx([BmnFixture::row(['Kode Barang' => 3100101001, 'NUP' => 7.0])]);

    app(ImportAssets::class)->handle($path);

    expect(Asset::firstOrFail()->asset_code)->toBe('3100101001-7');
});

it('keeps at most 100 error messages', function () {
    $rows = [];
    foreach (range(1, 105) as $nup) {
        $rows[] = BmnFixture::row(['NUP' => $nup, 'Golongan Aset' => 'BAD']);
    }

    $import = app(ImportAssets::class)->handle(BmnFixture::xlsx($rows));

    expect($import->failed)->toBe(105)
        ->and($import->errors)->toHaveCount(100);
});

it('imports through the artisan command', function () {
    $path = BmnFixture::xlsx([BmnFixture::row()]);

    $this->artisan('assets:import', ['path' => $path])->assertSuccessful();

    expect(Asset::count())->toBe(1);
});

it('fails the command when the file does not exist', function () {
    $this->artisan('assets:import', ['path' => 'C:/nope/missing.xlsx'])->assertFailed();
});
