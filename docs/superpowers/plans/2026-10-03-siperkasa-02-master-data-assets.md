# SiPerkasa Plan 2 — Master Data & BMN Asset Import

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Admins manage users, categories, buildings and rooms, and load the 25,995 BMN assets from Excel (CLI and UI) into a searchable read-only `assets` table.

**Architecture:** Schema is edited in place (repo has no released data). Spreadsheet reading uses streaming `openspout/openspout` behind `RowReader`. The importer (`ImportAssets`) is shared by an Artisan command and a queued job. Admin-only models share an `AdminOnly` policy trait.

**Tech Stack:** Laravel 13, openspout/openspout (new), Inertia 3 + Vue 3, Pest 5, MySQL 8.

**Spec:** `C:\Users\Lenovo\Documents\Obsidian Vault\40 - PROJECTS\SiPerkasa\04-Spec.md` (also `02-Data-Model.md`, `03-Tasks/F02-Master-Data.md`, `F03-Import-Aset.md`, `D:\siperkasa\.agents\rules\{siperkasa,db}.md`). Requires Plan 1 complete.

## Global Constraints

- BMN data is read-only in the app. `asset_code` = `{Kode Barang}-{NUP}`, unique. Import upserts by `asset_code`.
- Rows without Kode Barang or NUP (separator rows) are skipped, not counted as failures.
- Excel date serial `2958465` (9999-12-31) means empty → `null`. Dates arrive as serial numbers.
- All 80 original columns are kept in `assets.bmn_data` (JSON). `Kelompok Aset` → `categories.name`.
- Import chunk: 1,000 rows. Keep at most 100 errors in `asset_imports.errors`. Upload limit 20 MB, `.xlsx` only.
- Asset search: ≤ 20 results, minimum query length 2.
- Categories with assets, buildings with rooms cannot be deleted. `default_pic_id` must be an active user with role `pic`.
- Admins cannot deactivate or demote themselves. Users are deactivated, never deleted.
- UI is Bahasa Indonesia. Use design tokens (no raw hex). Touch targets ≥ 44 px (`h-11`).
- Paginated props use `->paginate()->through(fn …)` (props `data` + `links`); the frontend `Pagination` takes `links`.
- Commit messages must not contain any Claude attribution or `Co-Authored-By` trailer.

## Review Focus

- A numeric Kode Barang or a float NUP (`3100101001`, `1.0`) must produce `3100101001-1`, never `3.100101001E+9-1` (Task 2, `BmnValueTest`).
- Importing the same file twice must leave the row count unchanged and report `updated`, not `inserted` (Task 3).
- A row with an empty `Kelompok Aset` must import with `category_id = null`, not fail (Task 3).
- A users-import row whose email belongs to another user must be reported as failed, not crash the import (Task 6).
- An admin deactivating or demoting their own account must be refused (Task 6).

## File Structure

| File | Responsibility |
|---|---|
| `database/migrations/2026_09_28_09000{0,1}_create_{buildings,rooms}_table.php` | New; dated before `reports` so Plan 3's FK works |
| `database/migrations/…_create_{categories,assets}_table.php` | Rewritten in place |
| `database/migrations/2026_09_28_100003_create_asset_imports_table.php` | Import history |
| `app/Support/Bmn/{BmnColumns,BmnValue}.php` | 80 column names; cell → typed value conversion |
| `app/Support/Spreadsheet/RowReader.php` | Streams rows from xlsx/csv |
| `app/Actions/Assets/ImportAssets.php` | Core upsert importer |
| `app/Actions/Users/ImportUsers.php` | Users CSV/XLSX importer |
| `app/Policies/Concerns/AdminOnly.php` + 6 policies | Admin-only authorization |
| `tests/Support/BmnFixture.php` | Builds small BMN `.xlsx` files for tests |

---

### Task 1: Schema, models, factories, seeders

**Files:**
- Create: `database/migrations/2026_09_28_090000_create_buildings_table.php`, `…090001_create_rooms_table.php`, `…100003_create_asset_imports_table.php`, `app/Enums/AssetImportStatus.php`, `app/Models/{Building,Room,AssetImport}.php`, `database/factories/{Category,Building,Room,Asset}Factory.php`, `database/seeders/{CategorySeeder,BuildingSeeder}.php`, `tests/Feature/Assets/SchemaTest.php`
- Modify: `database/migrations/2026_09_28_100000_create_categories_table.php`, `…100001_create_assets_table.php`, `app/Models/{Category,Asset,User}.php`, `database/seeders/DatabaseSeeder.php`

**Interfaces:**
- Produces models/relations: `Category::{assets(), defaultPic(), isInUse()}`, `Building::{rooms(), isInUse()}`, `Room::{building(), isInUse()}`, `Asset::{category()}`, `User::defaultCategories()`. `isInUse()` is extended in Plan 3 to include reports.
- Produces factories: `Category::factory()`, `Building::factory()`, `Room::factory()`, `Asset::factory()`.
- Produces `App\Enums\AssetImportStatus` (`Queued='queued'`, `Processing='processing'`, `Done='done'`, `Failed='failed'`).

- [ ] **Step 1: Write the failing test** `tests/Feature/Assets/SchemaTest.php`

```php
<?php

use App\Models\Asset;
use App\Models\Building;
use App\Models\Category;
use App\Models\Room;
use App\Models\User;

test('assets belong to a category and keep the raw BMN row as JSON', function () {
    $category = Category::factory()->create();
    $asset = Asset::factory()->for($category)->create(['bmn_data' => ['Merk' => 'IBM']]);

    expect($asset->category->is($category))->toBeTrue();
    expect($asset->fresh()->bmn_data['Merk'])->toBe('IBM');
    expect($category->isInUse())->toBeTrue();
});

test('a category with a default pic exposes it', function () {
    $pic = User::factory()->pic()->create();
    $category = Category::factory()->create(['default_pic_id' => $pic->id]);

    expect($category->defaultPic->is($pic))->toBeTrue();
    expect($pic->defaultCategories)->toHaveCount(1);
});

test('rooms belong to buildings and are unique per building', function () {
    $building = Building::factory()->create();
    Room::factory()->for($building)->create(['code' => '101']);

    expect($building->isInUse())->toBeTrue();
    expect(fn () => Room::factory()->for($building)->create(['code' => '101']))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

test('an empty category and a building without rooms are not in use', function () {
    expect(Category::factory()->create()->isInUse())->toBeFalse();
    expect(Building::factory()->create()->isInUse())->toBeFalse();
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `php artisan test tests/Feature/Assets/SchemaTest.php`
Expected: FAIL (`Class "Database\Factories\CategoryFactory" not found` or similar).

- [ ] **Step 3: Rewrite `…100000_create_categories_table.php`** (whole file)

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->foreignId('default_pic_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
```

- [ ] **Step 4: Rewrite `…100001_create_assets_table.php`** (whole file)

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('asset_code')->unique();          // {Kode Barang}-{NUP}
            $table->string('kode_barang')->index();
            $table->unsignedInteger('nup');
            $table->string('name')->index();
            $table->string('brand')->nullable();
            $table->string('type')->nullable();
            $table->string('jenis_bmn');
            $table->enum('golongan', ['IT', 'NON-IT'])->index();
            $table->string('condition')->nullable();
            $table->date('acquired_at')->nullable();
            $table->decimal('acquisition_value', 18, 2)->nullable();
            $table->string('kode_register')->nullable()->index();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->json('bmn_data');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
```

- [ ] **Step 5: New migrations.** `2026_09_28_090000_create_buildings_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buildings', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buildings');
    }
};
```

`2026_09_28_090001_create_rooms_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('building_id')->constrained('buildings')->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->smallInteger('floor')->nullable();
            $table->timestamps();

            $table->unique(['building_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
```

`2026_09_28_100003_create_asset_imports_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('filename');
            $table->enum('status', ['queued', 'processing', 'done', 'failed'])->default('queued');
            $table->unsignedInteger('inserted')->default(0);
            $table->unsignedInteger('updated')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->json('errors')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_imports');
    }
};
```

- [ ] **Step 6: Enum** `app/Enums/AssetImportStatus.php`

```php
<?php

namespace App\Enums;

enum AssetImportStatus: string
{
    case Queued = 'queued';
    case Processing = 'processing';
    case Done = 'done';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Antre',
            self::Processing => 'Diproses',
            self::Done => 'Selesai',
            self::Failed => 'Gagal',
        };
    }
}
```

- [ ] **Step 7: Models.** `app/Models/Category.php` (whole file):

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property int|null $default_pic_id
 */
class Category extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'default_pic_id'];

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function defaultPic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'default_pic_id');
    }

    /** True when deleting this category would orphan data (extended in Plan 3 with reports). */
    public function isInUse(): bool
    {
        return $this->assets()->exists();
    }
}
```

`app/Models/Building.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 */
class Building extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'name'];

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function isInUse(): bool
    {
        return $this->rooms()->exists();
    }
}
```

`app/Models/Room.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $building_id
 * @property string $code
 * @property string $name
 * @property int|null $floor
 */
class Room extends Model
{
    use HasFactory;

    protected $fillable = ['building_id', 'code', 'name', 'floor'];

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    /** Extended in Plan 3 with reports. */
    public function isInUse(): bool
    {
        return false;
    }
}
```

`app/Models/Asset.php` (whole file):

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $asset_code
 * @property string $kode_barang
 * @property int $nup
 * @property string $name
 * @property string|null $brand
 * @property string|null $type
 * @property string $jenis_bmn
 * @property string $golongan
 * @property string|null $condition
 * @property \Illuminate\Support\Carbon|null $acquired_at
 * @property string|null $acquisition_value
 * @property string|null $kode_register
 * @property int|null $category_id
 * @property array<string, mixed> $bmn_data
 */
class Asset extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_code', 'kode_barang', 'nup', 'name', 'brand', 'type', 'jenis_bmn',
        'golongan', 'condition', 'acquired_at', 'acquisition_value', 'kode_register',
        'category_id', 'bmn_data',
    ];

    protected function casts(): array
    {
        return [
            'acquired_at' => 'date',
            'acquisition_value' => 'decimal:2',
            'bmn_data' => 'array',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }
}
```

`app/Models/AssetImport.php`:

```php
<?php

namespace App\Models;

use App\Enums\AssetImportStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $filename
 * @property AssetImportStatus $status
 * @property int $inserted
 * @property int $updated
 * @property int $failed
 * @property array<int, array{line: int, message: string}>|null $errors
 */
class AssetImport extends Model
{
    protected $fillable = ['user_id', 'filename', 'status', 'inserted', 'updated', 'failed', 'errors'];

    protected function casts(): array
    {
        return [
            'status' => AssetImportStatus::class,
            'errors' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

In `app/Models/User.php` replace the `picAssets()` method with:

```php
    /**
     * Categories whose default PIC is this user.
     */
    public function defaultCategories(): HasMany
    {
        return $this->hasMany(Category::class, 'default_pic_id');
    }
```

- [ ] **Step 8: Factories.** `database/factories/CategoryFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Category> */
class CategoryFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => fake()->unique()->words(2, true), 'default_pic_id' => null];
    }
}
```

`BuildingFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Building;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Building> */
class BuildingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('G??')),
            'name' => 'Gedung '.fake()->unique()->lastName(),
        ];
    }
}
```

`RoomFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Building;
use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Room> */
class RoomFactory extends Factory
{
    public function definition(): array
    {
        return [
            'building_id' => Building::factory(),
            'code' => fake()->unique()->numerify('R-###'),
            'name' => 'Ruang '.fake()->unique()->numerify('###'),
            'floor' => fake()->numberBetween(1, 4),
        ];
    }
}
```

`AssetFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Asset> */
class AssetFactory extends Factory
{
    public function definition(): array
    {
        $kode = fake()->unique()->numerify('3########0');
        $nup = fake()->numberBetween(1, 999);

        return [
            'asset_code' => "{$kode}-{$nup}",
            'kode_barang' => $kode,
            'nup' => $nup,
            'name' => fake()->randomElement(['Proyektor', 'AC Split', 'Kursi Kuliah', 'Komputer PC']).' '.fake()->numerify('##'),
            'brand' => fake()->randomElement(['Epson', 'Daikin', 'Lenovo', null]),
            'type' => null,
            'jenis_bmn' => 'MESIN PERALATAN NON TIK',
            'golongan' => 'NON-IT',
            'condition' => 'Baik',
            'acquired_at' => fake()->date(),
            'acquisition_value' => fake()->numberBetween(500_000, 20_000_000),
            'kode_register' => fake()->uuid(),
            'category_id' => Category::factory(),
            'bmn_data' => [],
        ];
    }
}
```

- [ ] **Step 9: Run the schema test**

Run: `php artisan test tests/Feature/Assets/SchemaTest.php`
Expected: 4 passed.

- [ ] **Step 10: Seeders.** `database/seeders/CategorySeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /** The 16 "Kelompok Aset" values found in the BMN export. */
    public const NAMES = [
        'Furnitur', 'Komputer & Server', 'Alat Laboratorium', 'Alat Kantor',
        'Periferal & Storage', 'Elektronik', 'Alat Medis', 'Audio & Studio',
        'Perangkat Jaringan', 'Kabel & Infrastruktur', 'Keamanan & Sistem IT',
        'Fasilitas & Safety', 'Alat Ukur', 'Lain-lain', 'Mesin & Perkakas',
        'Alat Komunikasi',
    ];

    public function run(): void
    {
        foreach (self::NAMES as $name) {
            Category::firstOrCreate(['name' => $name]);
        }
    }
}
```

`database/seeders/BuildingSeeder.php` (placeholder data, to be replaced with the real campus list):

```php
<?php

namespace Database\Seeders;

use App\Models\Building;
use Illuminate\Database\Seeder;

class BuildingSeeder extends Seeder
{
    /** Placeholder buildings — replace with the real campus list (see spec "Further Notes"). */
    public function run(): void
    {
        foreach (['A' => 'Gedung Contoh A', 'B' => 'Gedung Contoh B', 'C' => 'Gedung Contoh C'] as $code => $name) {
            $building = Building::firstOrCreate(['code' => "G{$code}"], ['name' => $name]);

            foreach ([['101', 'Ruang 101', 1], ['102', 'Ruang 102', 1], ['201', 'Ruang 201', 2], ['202', 'Ruang 202', 2]] as [$roomCode, $roomName, $floor]) {
                $building->rooms()->firstOrCreate(['code' => $roomCode], ['name' => $roomName, 'floor' => $floor]);
            }
        }
    }
}
```

In `DatabaseSeeder::run()`, immediately after the `User::updateOrCreate(...)` super-admin call and **before** the `if (! app()->environment('local')) { return; }` guard, add:

```php
        $this->call([CategorySeeder::class, BuildingSeeder::class]);
```

- [ ] **Step 11: Refresh the dev DB and run everything**

Run: `php artisan migrate:fresh --seed && php artisan test`
Expected: seeding succeeds; all tests pass.

- [ ] **Step 12: Commit**

```bash
git add -A
git commit -m "feat: categories with PIC, buildings, rooms, BMN asset schema and seeders"
```

---

### Task 2: BMN columns, value conversion, spreadsheet reader

**Files:**
- Create: `app/Support/Bmn/BmnColumns.php`, `app/Support/Bmn/BmnValue.php`, `app/Support/Spreadsheet/RowReader.php`, `tests/Unit/BmnValueTest.php`, `tests/Support/BmnFixture.php`

**Interfaces:**
- Produces:
  - `BmnColumns::ALL` (80 header names in file order) and constants `KODE_BARANG`, `NUP`, `NAMA_BARANG`, `MERK`, `TIPE`, `JENIS_BMN`, `KONDISI`, `TGL_PEROLEHAN`, `NILAI_PEROLEHAN`, `KODE_REGISTER`, `GOLONGAN`, `KELOMPOK`.
  - `BmnValue::string(mixed): ?string`, `::int(mixed): ?int`, `::decimal(mixed): ?string`, `::date(mixed): ?string` (`Y-m-d`), `::json(mixed): mixed`.
  - `RowReader::rows(string $path, string $extension): Generator<int, array>` and `RowReader::assoc(...)` (rows keyed by trimmed header; first row is the header).
  - `Tests\Support\BmnFixture::row(array $overrides = []): array` and `::xlsx(array $rows, bool $separator = true): string` (returns temp file path).

- [ ] **Step 1: Install the library**

Run: `composer require openspout/openspout`
Expected: package installed (v5.x on PHP 8.5). The API used in this plan (`Reader\XLSX\Reader`, `Reader\CSV\Reader`, `getSheetIterator()`, `getRowIterator()`, `Row::toArray()`, `Row::fromValues()`, `Writer\XLSX\Writer::openToFile()`) was checked against v5 while writing it: integral floats such as `7.0` come back as `7`, empty cells come back as `''`, and strings starting with `=` are written as formula cells.

- [ ] **Step 2: Write the failing unit test** `tests/Unit/BmnValueTest.php`

```php
<?php

use App\Support\Bmn\BmnValue;

test('numeric codes become clean strings', function () {
    expect(BmnValue::string(3100101001))->toBe('3100101001');
    expect(BmnValue::string(3100101001.0))->toBe('3100101001');
    expect(BmnValue::string('  ABC '))->toBe('ABC');
    expect(BmnValue::string(''))->toBeNull();
    expect(BmnValue::string(null))->toBeNull();
});

test('nup accepts ints, integral floats and numeric strings', function () {
    expect(BmnValue::int(1))->toBe(1);
    expect(BmnValue::int(1.0))->toBe(1);
    expect(BmnValue::int('12'))->toBe(12);
    expect(BmnValue::int('abc'))->toBeNull();
    expect(BmnValue::int(null))->toBeNull();
});

test('excel serial dates convert to ISO dates', function () {
    expect(BmnValue::date(39063))->toBe('2006-12-12');
    expect(BmnValue::date(42510))->toBe('2016-05-20');
    expect(BmnValue::date(39063.0))->toBe('2006-12-12');
});

test('the 9999-12-31 placeholder and blanks become null', function () {
    expect(BmnValue::date(2958465))->toBeNull();
    expect(BmnValue::date(0))->toBeNull();
    expect(BmnValue::date(null))->toBeNull();
    expect(BmnValue::date(''))->toBeNull();
    expect(BmnValue::date(new DateTimeImmutable('9999-12-31')))->toBeNull();
});

test('real date objects and ISO strings pass through', function () {
    expect(BmnValue::date(new DateTimeImmutable('2020-03-04 10:00:00')))->toBe('2020-03-04');
    expect(BmnValue::date('2020-03-04'))->toBe('2020-03-04');
    expect(BmnValue::date('not a date'))->toBeNull();
});

test('decimals are normalised to two places', function () {
    expect(BmnValue::decimal(1500000))->toBe('1500000.00');
    expect(BmnValue::decimal('1500000.5'))->toBe('1500000.50');
    expect(BmnValue::decimal(null))->toBeNull();
    expect(BmnValue::decimal('x'))->toBeNull();
});

test('json values keep numbers but turn dates and blanks into plain values', function () {
    expect(BmnValue::json(new DateTimeImmutable('2020-03-04')))->toBe('2020-03-04');
    expect(BmnValue::json(' '))->toBeNull();
    expect(BmnValue::json(2958465))->toBe(2958465);
    expect(BmnValue::json(5.0))->toBe(5);
});
```

- [ ] **Step 3: Run to verify it fails**

Run: `php artisan test tests/Unit/BmnValueTest.php`
Expected: FAIL (`Class "App\Support\Bmn\BmnValue" not found`).

- [ ] **Step 4: Create `app/Support/Bmn/BmnValue.php`**

```php
<?php

namespace App\Support\Bmn;

use Carbon\CarbonImmutable;
use DateTimeInterface;

final class BmnValue
{
    /** Excel serial for 9999-12-31, used by SIMAK/SAKTI as "no date". */
    private const EMPTY_DATE_SERIAL = 2958465;

    public static function string(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_float($value) && floor($value) === $value) {
            $value = (int) $value;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    public static function int(mixed $value): ?int
    {
        $text = self::string($value);

        return $text !== null && is_numeric($text) ? (int) $text : null;
    }

    public static function decimal(mixed $value): ?string
    {
        $text = self::string($value);

        return $text !== null && is_numeric($text) ? number_format((float) $text, 2, '.', '') : null;
    }

    /** @return string|null Y-m-d, or null for blanks, invalid values and the 9999 placeholder */
    public static function date(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return (int) $value->format('Y') >= 9999 ? null : $value->format('Y-m-d');
        }

        if (is_numeric($value)) {
            $serial = (int) $value;

            if ($serial <= 0 || $serial >= self::EMPTY_DATE_SERIAL) {
                return null;
            }

            return CarbonImmutable::create(1899, 12, 30)->addDays($serial)->format('Y-m-d');
        }

        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}/', trim($value)) === 1) {
            return CarbonImmutable::parse(trim($value))->format('Y-m-d');
        }

        return null;
    }

    /** Normalises a raw cell for storage inside the bmn_data JSON column. */
    public static function json(mixed $value): mixed
    {
        if ($value instanceof DateTimeInterface) {
            return self::date($value);
        }

        if (is_float($value) && floor($value) === $value) {
            return (int) $value;
        }

        if (is_string($value)) {
            $text = trim($value);

            return $text === '' ? null : $text;
        }

        return $value;
    }
}
```

- [ ] **Step 5: Create `app/Support/Bmn/BmnColumns.php`**

```php
<?php

namespace App\Support\Bmn;

final class BmnColumns
{
    public const JENIS_BMN = 'Jenis BMN';
    public const KODE_BARANG = 'Kode Barang';
    public const NUP = 'NUP';
    public const NAMA_BARANG = 'Nama Barang';
    public const MERK = 'Merk';
    public const TIPE = 'Tipe';
    public const KONDISI = 'Kondisi';
    public const TGL_PEROLEHAN = 'Tanggal Perolehan';
    public const NILAI_PEROLEHAN = 'Nilai Perolehan';
    public const KODE_REGISTER = 'Kode Register';
    public const GOLONGAN = 'Golongan Aset';
    public const KELOMPOK = 'Kelompok Aset';

    /** All 80 header names, in the order they appear in the export. */
    public const ALL = [
        'No', 'Jenis BMN', 'Kode Satker', 'Nama Satker', 'Kode Barang', 'NUP',
        'Nama Barang', 'Status BMN', 'Merk', 'Tipe', 'Kondisi', 'Umur Aset',
        'Intra / Extra', 'Henti Guna', 'Status SBSN', 'Status BMN Idle',
        'Status Kemitraan', 'BPYBDS', 'Usulan Barang Hilang', 'Usulan Barang RB',
        'Usul Hapus', 'Hibah DKTP', 'Konsensi Jasa', 'Properti Investasi',
        'Jenis Dokumen', 'No Dokumen', 'No BPKP', 'No Polisi',
        'Status Sertifikasi', 'Jenis Sertipikat', 'No Sertifikat', 'Nama',
        'Tanggal Buku Pertama', 'Tanggal Perolehan', 'Tanggal Pengapusan',
        'Nilai Perolehan Pertama', 'Nilai Mutasi', 'Nilai Perolehan',
        'Nilai Penyusutan', 'Nilai Buku', 'Luas Tanah Seluruhnya',
        'Luas Tanah Untuk Bangunan', 'Luas Tanah Untuk Sarana Lingkungan',
        'Luas Lahan Kosong', 'Luas Bangunan', 'Luas Tapak Bangunan',
        'Luas Pemanfataan', 'Jumlah Lantai', 'Jumlah Foto', 'Status Penggunaan',
        'No PSP', 'Tanggal PSP', 'Alamat', 'RT/RW', 'Kelurahan/Desa',
        'Kecamatan', 'Kab/Kota', 'Kode Kab/Kota', 'Provinsi', 'Kode Provinsi',
        'Kode Pos', 'SBSK', 'Optimalisasi', 'Penghuni', 'Pengguna',
        'Kode KPKNL', 'Uraian KPKNL', 'Uraian Kanwil DJKN', 'Nama K/L',
        'Nama E1', 'Nama Korwil', 'Kode Register', 'Lokasi Ruang',
        'Jenis Identitas', 'No Identitas', 'No STNK', 'Nama Pengguna',
        'Status PMK', 'Golongan Aset', 'Kelompok Aset',
    ];
}
```

- [ ] **Step 6: Create `app/Support/Spreadsheet/RowReader.php`**

```php
<?php

namespace App\Support\Spreadsheet;

use Generator;
use InvalidArgumentException;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

final class RowReader
{
    /**
     * Streams the first sheet row by row (low memory).
     *
     * @return Generator<int, array<int, mixed>> 1-based row number => cell values
     */
    public static function rows(string $path, string $extension): Generator
    {
        $reader = match (strtolower($extension)) {
            'xlsx' => new XlsxReader,
            'csv', 'txt' => new CsvReader,
            default => throw new InvalidArgumentException("Jenis berkas tidak didukung: {$extension}"),
        };

        $reader->open($path);

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $line = 0;

                foreach ($sheet->getRowIterator() as $row) {
                    yield ++$line => $row->toArray();
                }

                break;
            }
        } finally {
            $reader->close();
        }
    }

    /**
     * Same as rows(), but the first row is the header and every following row is keyed by it.
     *
     * @return Generator<int, array<string, mixed>>
     */
    public static function assoc(string $path, string $extension): Generator
    {
        $header = null;

        foreach (self::rows($path, $extension) as $line => $cells) {
            if ($header === null) {
                $header = array_map(fn ($name) => trim((string) $name), $cells);

                continue;
            }

            $row = [];

            foreach ($header as $index => $name) {
                if ($name !== '') {
                    $row[$name] = $cells[$index] ?? null;
                }
            }

            yield $line => $row;
        }
    }
}
```

- [ ] **Step 7: Create `tests/Support/BmnFixture.php`**

```php
<?php

namespace Tests\Support;

use App\Support\Bmn\BmnColumns;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

final class BmnFixture
{
    /** A realistic TIK asset row keyed by column name; override any column. */
    public static function row(array $overrides = []): array
    {
        return array_merge([
            'Jenis BMN' => 'MESIN PERALATAN KHUSUS TIK',
            'Kode Barang' => '3100101001',
            'NUP' => 1,
            'Nama Barang' => 'Mainframe (Komputer Jaringan)',
            'Merk' => 'IBM',
            'Kondisi' => 'Baik',
            'Tanggal Perolehan' => 39063,
            'Nilai Perolehan' => 1500000,
            'Kode Register' => 'REG-001',
            'Lokasi Ruang' => 'Belum berlokasi',
            'Golongan Aset' => 'IT',
            'Kelompok Aset' => 'Komputer & Server',
        ], $overrides);
    }

    /**
     * Writes an .xlsx shaped like the real export: header, one blank separator row
     * (only the two category columns filled), then the given rows.
     *
     * @param  array<int, array<string, mixed>>  $rows  column name => value (see row())
     * @return string absolute path of a temporary file
     */
    public static function xlsx(array $rows, bool $separator = true): string
    {
        $path = tempnam(sys_get_temp_dir(), 'bmn').'.xlsx';

        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(BmnColumns::ALL));

        if ($separator) {
            $writer->addRow(Row::fromValues(self::line([
                'Golongan Aset' => 'IT',
                'Kelompok Aset' => 'Lain-lain',
            ])));
        }

        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues(self::line($row)));
        }

        $writer->close();

        return $path;
    }

    private static function line(array $values): array
    {
        return array_map(fn (string $column) => $values[$column] ?? null, BmnColumns::ALL);
    }
}
```

- [ ] **Step 8: Run the unit test**

Run: `php artisan test tests/Unit/BmnValueTest.php`
Expected: 7 passed.

- [ ] **Step 9: Commit**

```bash
git add -A
git commit -m "feat: BMN column list, value conversion and streaming spreadsheet reader"
```

---

### Task 3: `ImportAssets` action and `assets:import` command

**Files:**
- Create: `app/Actions/Assets/ImportAssets.php`, `app/Console/Commands/ImportAssetsCommand.php`, `tests/Feature/Assets/ImportAssetsTest.php`

**Interfaces:**
- Consumes: `RowReader::assoc`, `BmnColumns`, `BmnValue`, `AssetImport`, `AssetImportStatus`, `Category`.
- Produces: `ImportAssets::handle(string $path, string $extension = 'xlsx', ?AssetImport $import = null, ?Closure $onProgress = null): AssetImport` — returns the (updated) `AssetImport` with `inserted`, `updated`, `failed`, `errors`, `status`. `$onProgress(int $rowsProcessed)` is called after each flushed batch. Artisan: `assets:import {path}`.

- [ ] **Step 1: Write the failing tests** `tests/Feature/Assets/ImportAssetsTest.php`

```php
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
```

- [ ] **Step 2: Run to verify it fails**

Run: `php artisan test tests/Feature/Assets/ImportAssetsTest.php`
Expected: FAIL (`Class "App\Actions\Assets\ImportAssets" not found`).

- [ ] **Step 3: Create `app/Actions/Assets/ImportAssets.php`**

```php
<?php

namespace App\Actions\Assets;

use App\Enums\AssetImportStatus;
use App\Models\Asset;
use App\Models\AssetImport;
use App\Models\Category;
use App\Support\Bmn\BmnColumns;
use App\Support\Bmn\BmnValue;
use App\Support\Spreadsheet\RowReader;
use Closure;
use Throwable;

final class ImportAssets
{
    private const MAX_ERRORS = 100;

    private const UPDATE_COLUMNS = [
        'kode_barang', 'nup', 'name', 'brand', 'type', 'jenis_bmn', 'golongan', 'condition',
        'acquired_at', 'acquisition_value', 'kode_register', 'category_id', 'bmn_data', 'updated_at',
    ];

    /** @var array<string, int> category name => id */
    private array $categoryIds = [];

    public function __construct(private readonly int $batchSize = 1000) {}

    public function handle(string $path, string $extension = 'xlsx', ?AssetImport $import = null, ?Closure $onProgress = null): AssetImport
    {
        $import ??= AssetImport::create(['filename' => basename($path), 'status' => AssetImportStatus::Processing]);
        $import->update(['status' => AssetImportStatus::Processing, 'inserted' => 0, 'updated' => 0, 'failed' => 0, 'errors' => null]);

        $stats = ['inserted' => 0, 'updated' => 0, 'failed' => 0, 'errors' => []];
        $batch = [];
        $processed = 0;

        try {
            foreach (RowReader::assoc($path, $extension) as $line => $row) {
                $kodeBarang = BmnValue::string($row[BmnColumns::KODE_BARANG] ?? null);
                $nup = BmnValue::int($row[BmnColumns::NUP] ?? null);

                if ($kodeBarang === null || $nup === null) {
                    continue; // separator row
                }

                $processed++;
                $error = $this->validate($row);

                if ($error !== null) {
                    $stats['failed']++;

                    if (count($stats['errors']) < self::MAX_ERRORS) {
                        $stats['errors'][] = ['line' => $line, 'message' => $error];
                    }

                    continue;
                }

                $code = "{$kodeBarang}-{$nup}";
                $batch[$code] = $this->toAttributes($code, $kodeBarang, $nup, $row);

                if (count($batch) >= $this->batchSize) {
                    $this->flush($batch, $stats);

                    if ($onProgress) {
                        $onProgress($processed);
                    }
                }
            }

            $this->flush($batch, $stats);

            if ($onProgress) {
                $onProgress($processed);
            }
        } catch (Throwable $e) {
            $import->update([
                'status' => AssetImportStatus::Failed,
                'inserted' => $stats['inserted'],
                'updated' => $stats['updated'],
                'failed' => $stats['failed'],
                'errors' => [...$stats['errors'], ['line' => 0, 'message' => $e->getMessage()]],
            ]);

            throw $e;
        }

        $import->update([
            'status' => AssetImportStatus::Done,
            'inserted' => $stats['inserted'],
            'updated' => $stats['updated'],
            'failed' => $stats['failed'],
            'errors' => $stats['errors'] ?: null,
        ]);

        return $import;
    }

    /** @param array<string, mixed> $row */
    private function validate(array $row): ?string
    {
        if (BmnValue::string($row[BmnColumns::NAMA_BARANG] ?? null) === null) {
            return 'Nama Barang kosong.';
        }

        if (BmnValue::string($row[BmnColumns::JENIS_BMN] ?? null) === null) {
            return 'Jenis BMN kosong.';
        }

        if (! in_array(BmnValue::string($row[BmnColumns::GOLONGAN] ?? null), ['IT', 'NON-IT'], true)) {
            return 'Golongan Aset harus IT atau NON-IT.';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function toAttributes(string $code, string $kodeBarang, int $nup, array $row): array
    {
        $now = now();
        $kelompok = BmnValue::string($row[BmnColumns::KELOMPOK] ?? null);

        return [
            'asset_code' => $code,
            'kode_barang' => $kodeBarang,
            'nup' => $nup,
            'name' => BmnValue::string($row[BmnColumns::NAMA_BARANG]),
            'brand' => BmnValue::string($row[BmnColumns::MERK] ?? null),
            'type' => BmnValue::string($row[BmnColumns::TIPE] ?? null),
            'jenis_bmn' => BmnValue::string($row[BmnColumns::JENIS_BMN]),
            'golongan' => BmnValue::string($row[BmnColumns::GOLONGAN]),
            'condition' => BmnValue::string($row[BmnColumns::KONDISI] ?? null),
            'acquired_at' => BmnValue::date($row[BmnColumns::TGL_PEROLEHAN] ?? null),
            'acquisition_value' => BmnValue::decimal($row[BmnColumns::NILAI_PEROLEHAN] ?? null),
            'kode_register' => BmnValue::string($row[BmnColumns::KODE_REGISTER] ?? null),
            'category_id' => $kelompok === null ? null : $this->categoryId($kelompok),
            'bmn_data' => json_encode(
                array_map(fn ($value) => BmnValue::json($value), $row),
                JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE,
            ),
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    private function categoryId(string $name): int
    {
        return $this->categoryIds[$name] ??= Category::firstOrCreate(['name' => $name])->id;
    }

    /**
     * @param  array<string, array<string, mixed>>  $batch
     * @param  array{inserted: int, updated: int, failed: int, errors: array}  $stats
     */
    private function flush(array &$batch, array &$stats): void
    {
        if ($batch === []) {
            return;
        }

        $codes = array_keys($batch);
        $existing = Asset::whereIn('asset_code', $codes)->count();

        $stats['updated'] += $existing;
        $stats['inserted'] += count($codes) - $existing;

        Asset::upsert(array_values($batch), ['asset_code'], self::UPDATE_COLUMNS);

        $batch = [];
    }
}
```

- [ ] **Step 4: Create `app/Console/Commands/ImportAssetsCommand.php`**

```php
<?php

namespace App\Console\Commands;

use App\Actions\Assets\ImportAssets;
use Illuminate\Console\Command;

class ImportAssetsCommand extends Command
{
    protected $signature = 'assets:import {path : Path to the BMN .xlsx file}';

    protected $description = 'Import or update BMN assets from an Excel file';

    public function handle(ImportAssets $importer): int
    {
        $path = (string) $this->argument('path');

        if (! is_file($path)) {
            $this->error("Berkas tidak ditemukan: {$path}");

            return self::FAILURE;
        }

        $this->info('Mengimpor aset…');

        $import = $importer->handle(
            $path,
            pathinfo($path, PATHINFO_EXTENSION) ?: 'xlsx',
            null,
            fn (int $rows) => $this->output->write("\r{$rows} baris diproses"),
        );

        $this->newLine(2);
        $this->table(['Ditambah', 'Diperbarui', 'Gagal'], [[$import->inserted, $import->updated, $import->failed]]);

        foreach (array_slice($import->errors ?? [], 0, 10) as $error) {
            $this->warn("Baris {$error['line']}: {$error['message']}");
        }

        return self::SUCCESS;
    }
}
```

- [ ] **Step 5: Run the tests**

Run: `php artisan test tests/Feature/Assets/ImportAssetsTest.php`
Expected: 10 passed.

- [ ] **Step 6: Try the real file** (local only, not part of the suite)

Run: `php artisan assets:import data/daftar-aset-gabungan-terkategori.xlsx`
Expected: table shows `Ditambah 25995`, `Diperbarui 0`, `Gagal 0`; run it again → `Ditambah 0`, `Diperbarui 25995`. Then `php artisan tinker --execute="echo App\Models\Asset::count().' / '.App\Models\Category::count();"` prints `25995 / 16`. If `Gagal` is not 0, read the warnings and report them before continuing.

- [ ] **Step 7: Lint and commit**

Run: `composer lint:check && composer types:check`
Expected: no errors (run `composer lint` to fix style).

```bash
git add -A
git commit -m "feat: import BMN assets from Excel via command (idempotent upsert)"
```

---

### Task 4: Asset import UI, asset browser and search endpoint

**Files:**
- Create: `app/Policies/Concerns/AdminOnly.php`, `app/Policies/{Asset,AssetImport,Category,Building,Room}Policy.php`, `app/Jobs/ImportAssetsJob.php`, `app/Http/Requests/Admin/StoreAssetImportRequest.php`, `app/Http/Controllers/Admin/{AssetController,AssetImportController}.php`, `app/Http/Controllers/AssetSearchController.php`, `resources/js/pages/admin/assets/{Index,Show,Imports}.vue`, `tests/Feature/Admin/{AssetImportUploadTest,AssetBrowserTest}.php`, `tests/Feature/Assets/AssetSearchTest.php`
- Modify: `routes/web.php`, `resources/js/composables/useRoleNav.ts`

**Interfaces:**
- Produces:
  - `App\Policies\Concerns\AdminOnly` trait (methods `viewAny/view/create/update/delete` → admin only), used by every admin-only policy.
  - Routes: `admin.assets.index`, `admin.assets.show`, `admin.asset-imports.index`, `admin.asset-imports.store`, `assets.search` (any active user).
  - `GET /assets/search?q=` → JSON `{ "data": [ { "id": int, "asset_code": string, "name": string, "brand": string|null, "category": string|null } ] }`, max 20 rows, `[]` when `q` shorter than 2 characters.

- [ ] **Step 1: Write the failing tests.** `tests/Feature/Assets/AssetSearchTest.php`

```php
<?php

use App\Models\Asset;
use App\Models\User;

test('guests can not search assets', function () {
    $this->getJson(route('assets.search', ['q' => 'proyektor']))->assertUnauthorized();
});

test('any logged-in role can search by name or code', function (string $state) {
    Asset::factory()->create(['name' => 'Proyektor Epson', 'asset_code' => '3050101001-1']);
    Asset::factory()->create(['name' => 'Kursi Kuliah']);

    $this->actingAs(User::factory()->{$state}()->create())
        ->getJson(route('assets.search', ['q' => 'proyek']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Proyektor Epson');

    $this->actingAs(User::factory()->{$state}()->create())
        ->getJson(route('assets.search', ['q' => '3050101001']))
        ->assertJsonCount(1, 'data');
})->with(['reporter', 'admin', 'pic']);

test('results are capped at 20', function () {
    Asset::factory()->count(25)->create(['name' => 'Kursi Kuliah']);

    $this->actingAs(User::factory()->create())
        ->getJson(route('assets.search', ['q' => 'kursi']))
        ->assertJsonCount(20, 'data');
});

test('short queries and wildcard characters return nothing harmful', function () {
    Asset::factory()->count(3)->create();
    $user = User::factory()->create();

    $this->actingAs($user)->getJson(route('assets.search', ['q' => 'a']))->assertJsonCount(0, 'data');
    $this->actingAs($user)->getJson(route('assets.search', ['q' => '%%']))->assertJsonCount(0, 'data');
});
```

`tests/Feature/Admin/AssetImportUploadTest.php`:

```php
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
```

`tests/Feature/Admin/AssetBrowserTest.php`:

```php
<?php

use App\Models\Asset;
use App\Models\Category;
use App\Models\User;

test('admin browses assets with filters', function () {
    $laptops = Category::factory()->create(['name' => 'Komputer & Server']);
    Asset::factory()->for($laptops)->create(['name' => 'Laptop Dosen', 'golongan' => 'IT', 'condition' => 'Rusak Berat']);
    Asset::factory()->count(2)->create(['golongan' => 'NON-IT']);

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('admin.assets.index'))
        ->assertInertia(fn ($page) => $page->component('admin/assets/Index')->has('assets.data', 3));

    $this->actingAs($admin)->get(route('admin.assets.index', ['q' => 'laptop']))
        ->assertInertia(fn ($page) => $page->has('assets.data', 1));

    $this->actingAs($admin)->get(route('admin.assets.index', ['golongan' => 'IT']))
        ->assertInertia(fn ($page) => $page->has('assets.data', 1));

    $this->actingAs($admin)->get(route('admin.assets.index', ['category_id' => $laptops->id, 'condition' => 'Rusak Berat']))
        ->assertInertia(fn ($page) => $page->has('assets.data', 1));
});

test('asset detail shows the raw BMN data and offers no edit routes', function () {
    $asset = Asset::factory()->create(['bmn_data' => ['Merk' => 'IBM']]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.assets.show', $asset))
        ->assertInertia(fn ($page) => $page
            ->component('admin/assets/Show')
            ->where('asset.bmn_data.Merk', 'IBM'));

    $this->actingAs(User::factory()->admin()->create())
        ->put("/admin/assets/{$asset->id}", ['name' => 'x'])
        ->assertStatus(405);
});

test('non-admins can not browse assets', function () {
    $this->actingAs(User::factory()->pic()->create())->get(route('admin.assets.index'))->assertForbidden();
});
```

- [ ] **Step 2: Run to verify they fail**

Run: `php artisan test tests/Feature/Assets/AssetSearchTest.php tests/Feature/Admin`
Expected: FAIL (`Route [assets.search] not defined`).

- [ ] **Step 3: Policies.** `app/Policies/Concerns/AdminOnly.php`:

```php
<?php

namespace App\Policies\Concerns;

use App\Enums\UserRole;
use App\Models\User;

trait AdminOnly
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(UserRole::Admin);
    }

    public function view(User $user, mixed $model = null): bool
    {
        return $user->hasRole(UserRole::Admin);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::Admin);
    }

    public function update(User $user, mixed $model = null): bool
    {
        return $user->hasRole(UserRole::Admin);
    }

    public function delete(User $user, mixed $model = null): bool
    {
        return $user->hasRole(UserRole::Admin);
    }
}
```

Create the five policies; each has the same body (shown once for `AssetPolicy`, repeat for `AssetImportPolicy`, `CategoryPolicy`, `BuildingPolicy`, `RoomPolicy`, changing only the class name):

```php
<?php

namespace App\Policies;

use App\Policies\Concerns\AdminOnly;

class AssetPolicy
{
    use AdminOnly;
}
```

- [ ] **Step 4: Job** `app/Jobs/ImportAssetsJob.php`

```php
<?php

namespace App\Jobs;

use App\Actions\Assets\ImportAssets;
use App\Models\AssetImport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class ImportAssetsJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public function __construct(public int $importId, public string $storagePath) {}

    public function handle(ImportAssets $importer): void
    {
        $import = AssetImport::findOrFail($this->importId);

        try {
            $importer->handle(Storage::disk('local')->path($this->storagePath), 'xlsx', $import);
        } finally {
            Storage::disk('local')->delete($this->storagePath);
        }
    }
}
```

- [ ] **Step 5: Request** `app/Http/Requests/Admin/StoreAssetImportRequest.php`

```php
<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreAssetImportRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx', 'max:20480'],
        ];
    }
}
```

- [ ] **Step 6: Controllers.** `app/Http/Controllers/Admin/AssetImportController.php`

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AssetImportStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAssetImportRequest;
use App\Jobs\ImportAssetsJob;
use App\Models\AssetImport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AssetImportController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', AssetImport::class);

        return Inertia::render('admin/assets/Imports', [
            'imports' => AssetImport::query()->with('user:id,name')->latest()->limit(20)->get()
                ->map(fn (AssetImport $import) => [
                    'id' => $import->id,
                    'filename' => $import->filename,
                    'status' => $import->status->value,
                    'status_label' => $import->status->label(),
                    'inserted' => $import->inserted,
                    'updated' => $import->updated,
                    'failed' => $import->failed,
                    'errors' => $import->errors ?? [],
                    'user' => $import->user?->name,
                    'created_at' => $import->created_at?->toIso8601String(),
                ]),
        ]);
    }

    public function store(StoreAssetImportRequest $request): RedirectResponse
    {
        Gate::authorize('create', AssetImport::class);

        $file = $request->file('file');
        $path = $file->storeAs('imports', Str::uuid().'.xlsx', 'local');

        $import = AssetImport::create([
            'user_id' => $request->user()->id,
            'filename' => $file->getClientOriginalName(),
            'status' => AssetImportStatus::Queued,
        ]);

        ImportAssetsJob::dispatch($import->id, $path);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Berkas diterima. Impor sedang diproses.']);

        return to_route('admin.asset-imports.index');
    }
}
```

`app/Http/Controllers/Admin/AssetController.php`

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AssetController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Asset::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer'],
            'golongan' => ['nullable', 'in:IT,NON-IT'],
            'condition' => ['nullable', 'string', 'max:50'],
        ]);

        $assets = Asset::query()
            ->with('category:id,name')
            ->when($filters['q'] ?? null, function ($query, string $q) {
                $like = addcslashes($q, '%_\\');
                $query->where(fn ($inner) => $inner->where('asset_code', 'like', "{$like}%")->orWhere('name', 'like', "%{$like}%"));
            })
            ->when($filters['category_id'] ?? null, fn ($query, $id) => $query->where('category_id', $id))
            ->when($filters['golongan'] ?? null, fn ($query, $golongan) => $query->where('golongan', $golongan))
            ->when($filters['condition'] ?? null, fn ($query, $condition) => $query->where('condition', $condition))
            ->orderBy('asset_code')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Asset $asset) => [
                'id' => $asset->id,
                'asset_code' => $asset->asset_code,
                'name' => $asset->name,
                'brand' => $asset->brand,
                'golongan' => $asset->golongan,
                'condition' => $asset->condition,
                'category' => $asset->category?->name,
            ]);

        return Inertia::render('admin/assets/Index', [
            'assets' => $assets,
            'filters' => $filters,
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'conditions' => Asset::query()->whereNotNull('condition')->distinct()->orderBy('condition')->pluck('condition'),
        ]);
    }

    public function show(Asset $asset): Response
    {
        Gate::authorize('view', $asset);

        return Inertia::render('admin/assets/Show', [
            'asset' => [
                'id' => $asset->id,
                'asset_code' => $asset->asset_code,
                'name' => $asset->name,
                'brand' => $asset->brand,
                'type' => $asset->type,
                'jenis_bmn' => $asset->jenis_bmn,
                'golongan' => $asset->golongan,
                'condition' => $asset->condition,
                'acquired_at' => $asset->acquired_at?->toDateString(),
                'acquisition_value' => $asset->acquisition_value,
                'category' => $asset->category?->name,
                'bmn_data' => $asset->bmn_data,
            ],
        ]);
    }
}
```

`app/Http/Controllers/AssetSearchController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssetSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $like = addcslashes($q, '%_\\');

        if (mb_strlen($q) < 2 || trim($like, '\\%_') === '') {
            return response()->json(['data' => []]);
        }

        $assets = Asset::query()
            ->with('category:id,name')
            ->where(fn ($query) => $query->where('asset_code', 'like', "{$like}%")->orWhere('name', 'like', "%{$like}%"))
            ->orderBy('name')
            ->limit(20)
            ->get()
            ->map(fn (Asset $asset) => [
                'id' => $asset->id,
                'asset_code' => $asset->asset_code,
                'name' => $asset->name,
                'brand' => $asset->brand,
                'category' => $asset->category?->name,
            ]);

        return response()->json(['data' => $assets]);
    }
}
```

Note: `'%%'` is escaped to `\%\%`; the `trim($like, '\\%_') === ''` guard rejects queries made only of wildcard characters.

- [ ] **Step 7: Routes.** In `routes/web.php` add the imports `use App\Http\Controllers\Admin\AssetController; use App\Http\Controllers\Admin\AssetImportController; use App\Http\Controllers\AssetSearchController;`. Inside the `['auth', 'active']` group, before the role groups, add:

```php
    Route::get('assets/search', AssetSearchController::class)->name('assets.search');
```

Inside the `role:admin` group add:

```php
        Route::get('assets', [AssetController::class, 'index'])->name('assets.index');
        Route::get('assets/{asset}', [AssetController::class, 'show'])->name('assets.show');
        Route::get('asset-imports', [AssetImportController::class, 'index'])->name('asset-imports.index');
        Route::post('asset-imports', [AssetImportController::class, 'store'])->name('asset-imports.store');
```

`getJson` for a guest on an `auth` route returns 401 (JSON requests do not redirect) ✓.

- [ ] **Step 8: Pages.** `resources/js/pages/admin/assets/Index.vue`

```vue
<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { index, show } from '@/routes/admin/assets';

type AssetRow = {
    id: number;
    asset_code: string;
    name: string;
    brand: string | null;
    golongan: string;
    condition: string | null;
    category: string | null;
};

const props = defineProps<{
    assets: { data: AssetRow[]; links: { url: string | null; label: string; active: boolean }[] };
    filters: { q?: string; category_id?: string | number; golongan?: string; condition?: string };
    categories: { id: number; name: string }[];
    conditions: string[];
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Aset', href: index() }] },
});

const q = ref(props.filters.q ?? '');
const category = ref(String(props.filters.category_id ?? ''));
const golongan = ref(props.filters.golongan ?? '');
const condition = ref(props.filters.condition ?? '');

function apply() {
    router.get(
        index.url(),
        {
            q: q.value || undefined,
            category_id: category.value || undefined,
            golongan: golongan.value || undefined,
            condition: condition.value || undefined,
        },
        { preserveState: true, replace: true },
    );
}
</script>

<template>
    <Head title="Aset BMN" />

    <div class="flex flex-col gap-4 p-4">
        <PageHeader title="Aset BMN" description="Data aset hanya bisa dilihat. Perbarui lewat impor." />

        <form class="grid gap-2 md:grid-cols-5" @submit.prevent="apply">
            <Input v-model="q" class="h-11 md:col-span-2" placeholder="Cari kode atau nama aset" aria-label="Cari aset" />
            <NativeSelect v-model="category" aria-label="Kategori">
                <option value="">Semua kategori</option>
                <option v-for="c in categories" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
            </NativeSelect>
            <NativeSelect v-model="golongan" aria-label="Golongan">
                <option value="">IT &amp; NON-IT</option>
                <option value="IT">IT</option>
                <option value="NON-IT">NON-IT</option>
            </NativeSelect>
            <NativeSelect v-model="condition" aria-label="Kondisi">
                <option value="">Semua kondisi</option>
                <option v-for="c in conditions" :key="c" :value="c">{{ c }}</option>
            </NativeSelect>
            <Button type="submit" class="h-11 md:col-span-5 md:w-fit">Terapkan filter</Button>
        </form>

        <EmptyState v-if="assets.data.length === 0" title="Aset tidak ditemukan" description="Ubah filter atau impor data BMN terlebih dahulu." />

        <ul v-else class="divide-y rounded-xl border">
            <li v-for="asset in assets.data" :key="asset.id">
                <Link :href="show.url(asset.id)" class="flex flex-col gap-1 p-3 hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none md:flex-row md:items-center md:justify-between">
                    <span class="min-w-0">
                        <span class="block truncate font-medium">{{ asset.name }}</span>
                        <span class="block text-sm text-muted-foreground">{{ asset.asset_code }} · {{ asset.brand ?? 'Tanpa merk' }}</span>
                    </span>
                    <span class="text-sm text-muted-foreground">
                        {{ asset.category ?? 'Tanpa kategori' }} · {{ asset.golongan }} · {{ asset.condition ?? '-' }}
                    </span>
                </Link>
            </li>
        </ul>

        <Pagination :links="assets.links" />
    </div>
</template>
```

`resources/js/pages/admin/assets/Show.vue`

```vue
<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/components/PageHeader.vue';
import { formatDate, formatRupiah } from '@/lib/format';
import { index } from '@/routes/admin/assets';

const props = defineProps<{
    asset: {
        id: number;
        asset_code: string;
        name: string;
        brand: string | null;
        type: string | null;
        jenis_bmn: string;
        golongan: string;
        condition: string | null;
        acquired_at: string | null;
        acquisition_value: string | null;
        category: string | null;
        bmn_data: Record<string, unknown>;
    };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Aset', href: index() }] },
});

const summary: [string, string][] = [
    ['Kode aset', props.asset.asset_code],
    ['Merk / tipe', [props.asset.brand, props.asset.type].filter(Boolean).join(' / ') || '-'],
    ['Jenis BMN', props.asset.jenis_bmn],
    ['Golongan', props.asset.golongan],
    ['Kategori', props.asset.category ?? '-'],
    ['Kondisi', props.asset.condition ?? '-'],
    ['Tanggal perolehan', formatDate(props.asset.acquired_at)],
    ['Nilai perolehan', formatRupiah(props.asset.acquisition_value)],
];
</script>

<template>
    <Head :title="asset.name" />

    <div class="flex flex-col gap-6 p-4">
        <PageHeader :title="asset.name" description="Data BMN bersifat read-only." />

        <dl class="grid gap-3 rounded-xl border p-4 sm:grid-cols-2">
            <div v-for="[label, value] in summary" :key="label">
                <dt class="text-sm text-muted-foreground">{{ label }}</dt>
                <dd class="font-medium break-words">{{ value }}</dd>
            </div>
        </dl>

        <section aria-labelledby="bmn-heading">
            <h2 id="bmn-heading" class="mb-2 font-semibold">Seluruh kolom BMN</h2>
            <dl class="grid gap-x-6 gap-y-2 rounded-xl border p-4 text-sm sm:grid-cols-2">
                <div v-for="(value, key) in asset.bmn_data" :key="key" class="flex flex-col">
                    <dt class="text-muted-foreground">{{ key }}</dt>
                    <dd class="break-words">{{ value ?? '-' }}</dd>
                </div>
            </dl>
        </section>
    </div>
</template>
```

`resources/js/pages/admin/assets/Imports.vue`

```vue
<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, computed } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDateTime } from '@/lib/format';
import { index, store } from '@/routes/admin/asset-imports';

type ImportRow = {
    id: number;
    filename: string;
    status: 'queued' | 'processing' | 'done' | 'failed';
    status_label: string;
    inserted: number;
    updated: number;
    failed: number;
    errors: { line: number; message: string }[];
    user: string | null;
    created_at: string;
};

const props = defineProps<{ imports: ImportRow[] }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Impor Aset', href: index() }] },
});

const form = useForm<{ file: File | null }>({ file: null });

function submit() {
    form.post(store.url(), {
        forceFormData: true,
        onSuccess: () => form.reset(),
    });
}

const running = computed(() => props.imports.some((i) => i.status === 'queued' || i.status === 'processing'));
let timer: ReturnType<typeof setInterval> | undefined;

onMounted(() => {
    timer = setInterval(() => {
        if (running.value) router.reload({ only: ['imports'] });
    }, 3000);
});
onBeforeUnmount(() => clearInterval(timer));
</script>

<template>
    <Head title="Impor Aset" />

    <div class="flex flex-col gap-6 p-4">
        <PageHeader title="Impor Aset BMN" description="Unggah file Excel BMN (.xlsx, maks 20 MB). Aset yang sudah ada akan diperbarui." />

        <form class="grid gap-2 rounded-xl border p-4 sm:max-w-xl" @submit.prevent="submit">
            <Label for="file">Berkas Excel</Label>
            <Input id="file" type="file" accept=".xlsx" class="h-11" @input="form.file = ($event.target as HTMLInputElement).files?.[0] ?? null" />
            <InputError :message="form.errors.file" />
            <Button type="submit" class="h-11 w-fit" :disabled="form.processing || !form.file">Unggah dan impor</Button>
        </form>

        <EmptyState v-if="imports.length === 0" title="Belum ada impor" description="Riwayat impor akan tampil di sini." />

        <ul v-else class="divide-y rounded-xl border">
            <li v-for="item in imports" :key="item.id" class="flex flex-col gap-1 p-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="font-medium">{{ item.filename }}</span>
                    <span class="text-sm font-semibold" :class="item.status === 'failed' ? 'text-destructive' : item.status === 'done' ? 'text-status-closed' : 'text-status-in-progress'">
                        {{ item.status_label }}
                    </span>
                </div>
                <p class="text-sm text-muted-foreground">
                    {{ formatDateTime(item.created_at) }} · {{ item.user ?? 'Sistem' }} ·
                    ditambah {{ item.inserted }}, diperbarui {{ item.updated }}, gagal {{ item.failed }}
                </p>
                <ul v-if="item.errors.length" class="mt-1 list-disc pl-5 text-sm text-destructive">
                    <li v-for="(error, i) in item.errors.slice(0, 5)" :key="i">Baris {{ error.line }}: {{ error.message }}</li>
                </ul>
            </li>
        </ul>
    </div>
</template>
```

- [ ] **Step 9: Nav entries.** In `resources/js/composables/useRoleNav.ts` import `Boxes, Upload` from `@lucide/vue`, import `index as assetsIndex` from `@/routes/admin/assets` and `index as assetImportsIndex` from `@/routes/admin/asset-imports`, and append to `adminNav`:

```ts
    { title: 'Aset', href: assetsIndex(), icon: Boxes },
    { title: 'Impor Aset', href: assetImportsIndex(), icon: Upload },
```

- [ ] **Step 10: Run tests and frontend checks**

Run: `php artisan test && php artisan wayfinder:generate && npm run types:check && npm run check`
Expected: all pass.

- [ ] **Step 11: Commit**

```bash
git add -A
git commit -m "feat: asset import upload, asset browser and asset search endpoint"
```

---

### Task 5: Categories, buildings and rooms CRUD

**Files:**
- Create: `app/Http/Requests/Admin/{SaveCategoryRequest,SaveBuildingRequest,SaveRoomRequest}.php`, `app/Http/Controllers/Admin/{CategoryController,BuildingController,RoomController}.php`, `resources/js/pages/admin/categories/Index.vue`, `resources/js/pages/admin/buildings/{Index,Show}.vue`, `tests/Feature/Admin/{CategoryTest,BuildingRoomTest}.php`
- Modify: `routes/web.php`, `resources/js/composables/useRoleNav.ts`

**Interfaces:**
- Consumes: policies (Task 4), `Category::isInUse()`, `Building::isInUse()`, `Room::isInUse()`.
- Produces routes: `admin.categories.{index,store,update,destroy}`, `admin.buildings.{index,show,store,update,destroy}`, `admin.buildings.rooms.store`, `admin.rooms.{update,destroy}`.

- [ ] **Step 1: Write the failing tests.** `tests/Feature/Admin/CategoryTest.php`

```php
<?php

use App\Models\Asset;
use App\Models\Category;
use App\Models\User;

beforeEach(fn () => $this->admin = User::factory()->admin()->create());

test('admin sees categories with their pic and asset counts', function () {
    $pic = User::factory()->pic()->create();
    $category = Category::factory()->create(['name' => 'Elektronik', 'default_pic_id' => $pic->id]);
    Asset::factory()->count(2)->for($category)->create();

    $this->actingAs($this->admin)->get(route('admin.categories.index'))
        ->assertInertia(fn ($page) => $page
            ->component('admin/categories/Index')
            ->where('categories.0.name', 'Elektronik')
            ->where('categories.0.default_pic_name', $pic->name)
            ->where('categories.0.assets_count', 2)
            ->has('pics', 1));
});

test('admin creates and updates a category with a default pic', function () {
    $pic = User::factory()->pic()->create();

    $this->actingAs($this->admin)->post(route('admin.categories.store'), ['name' => 'Furnitur', 'default_pic_id' => $pic->id])
        ->assertRedirect();

    $category = Category::where('name', 'Furnitur')->firstOrFail();
    expect($category->default_pic_id)->toBe($pic->id);

    $this->actingAs($this->admin)->put(route('admin.categories.update', $category), ['name' => 'Furnitur Kampus', 'default_pic_id' => null])
        ->assertRedirect();

    expect($category->fresh()->name)->toBe('Furnitur Kampus')
        ->and($category->fresh()->default_pic_id)->toBeNull();
});

test('only active pic users can be the default pic', function (string $state, bool $active) {
    $user = User::factory()->{$state}()->create(['is_active' => $active]);

    $this->actingAs($this->admin)->post(route('admin.categories.store'), ['name' => 'X', 'default_pic_id' => $user->id])
        ->assertSessionHasErrors('default_pic_id');

    expect(Category::count())->toBe(0);
})->with([['reporter', true], ['admin', true], ['pic', false]]);

test('category names are unique', function () {
    Category::factory()->create(['name' => 'Elektronik']);

    $this->actingAs($this->admin)->post(route('admin.categories.store'), ['name' => 'Elektronik'])
        ->assertSessionHasErrors('name');
});

test('a category in use can not be deleted, an empty one can', function () {
    $used = Category::factory()->create();
    Asset::factory()->for($used)->create();
    $empty = Category::factory()->create();

    $this->actingAs($this->admin)->delete(route('admin.categories.destroy', $used));
    $this->actingAs($this->admin)->delete(route('admin.categories.destroy', $empty));

    expect($used->fresh())->not->toBeNull()
        ->and($empty->fresh())->toBeNull();
});

test('non-admins are forbidden', function (string $state) {
    $user = User::factory()->{$state}()->create();

    $this->actingAs($user)->get(route('admin.categories.index'))->assertForbidden();
    $this->actingAs($user)->post(route('admin.categories.store'), ['name' => 'X'])->assertForbidden();
})->with(['reporter', 'pic']);
```

`tests/Feature/Admin/BuildingRoomTest.php`

```php
<?php

use App\Models\Building;
use App\Models\Room;
use App\Models\User;

beforeEach(fn () => $this->admin = User::factory()->admin()->create());

test('admin manages buildings', function () {
    $this->actingAs($this->admin)->post(route('admin.buildings.store'), ['code' => 'GA', 'name' => 'Gedung A'])->assertRedirect();
    $building = Building::where('code', 'GA')->firstOrFail();

    $this->actingAs($this->admin)->put(route('admin.buildings.update', $building), ['code' => 'GA', 'name' => 'Gedung Rektorat'])->assertRedirect();
    expect($building->fresh()->name)->toBe('Gedung Rektorat');

    $this->actingAs($this->admin)->delete(route('admin.buildings.destroy', $building));
    expect(Building::count())->toBe(0);
});

test('building codes are unique', function () {
    Building::factory()->create(['code' => 'GA']);

    $this->actingAs($this->admin)->post(route('admin.buildings.store'), ['code' => 'GA', 'name' => 'Lain'])
        ->assertSessionHasErrors('code');
});

test('a building that still has rooms can not be deleted', function () {
    $building = Building::factory()->create();
    Room::factory()->for($building)->create();

    $this->actingAs($this->admin)->delete(route('admin.buildings.destroy', $building));

    expect($building->fresh())->not->toBeNull();
});

test('the building page lists its rooms', function () {
    $building = Building::factory()->create();
    Room::factory()->count(2)->for($building)->create();

    $this->actingAs($this->admin)->get(route('admin.buildings.show', $building))
        ->assertInertia(fn ($page) => $page->component('admin/buildings/Show')->has('rooms', 2));
});

test('admin manages rooms and codes are unique per building only', function () {
    $a = Building::factory()->create();
    $b = Building::factory()->create();

    $this->actingAs($this->admin)->post(route('admin.buildings.rooms.store', $a), ['code' => '101', 'name' => 'Ruang 101', 'floor' => 1])->assertRedirect();
    $this->actingAs($this->admin)->post(route('admin.buildings.rooms.store', $a), ['code' => '101', 'name' => 'Duplikat'])->assertSessionHasErrors('code');
    $this->actingAs($this->admin)->post(route('admin.buildings.rooms.store', $b), ['code' => '101', 'name' => 'Ruang 101 B'])->assertSessionHasNoErrors();

    $room = Room::where('building_id', $a->id)->firstOrFail();
    $this->actingAs($this->admin)->put(route('admin.rooms.update', $room), ['code' => '101', 'name' => 'Lab Komputer', 'floor' => 2])->assertSessionHasNoErrors();
    expect($room->fresh()->name)->toBe('Lab Komputer');

    $this->actingAs($this->admin)->delete(route('admin.rooms.destroy', $room));
    expect($room->fresh())->toBeNull();
});

test('non-admins are forbidden', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.buildings.index'))->assertForbidden();
});
```

- [ ] **Step 2: Run to verify they fail**

Run: `php artisan test tests/Feature/Admin/CategoryTest.php tests/Feature/Admin/BuildingRoomTest.php`
Expected: FAIL (`Route [admin.categories.index] not defined`).

- [ ] **Step 3: Requests.** `SaveCategoryRequest.php`

```php
<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCategoryRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('categories', 'name')->ignore($this->route('category'))],
            'default_pic_id' => [
                'nullable',
                Rule::exists('users', 'id')->where('role', UserRole::Pic->value)->where('is_active', true),
            ],
        ];
    }
}
```

`SaveBuildingRequest.php`

```php
<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveBuildingRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20', Rule::unique('buildings', 'code')->ignore($this->route('building'))],
            'name' => ['required', 'string', 'max:150'],
        ];
    }
}
```

`SaveRoomRequest.php` (the building comes from the route for `store`, from the room for `update`)

```php
<?php

namespace App\Http\Requests\Admin;

use App\Models\Room;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveRoomRequest extends FormRequest
{
    public function rules(): array
    {
        $room = $this->route('room');
        $buildingId = $room instanceof Room ? $room->building_id : $this->route('building')->id;

        return [
            'code' => [
                'required', 'string', 'max:30',
                Rule::unique('rooms', 'code')->where('building_id', $buildingId)->ignore($room),
            ],
            'name' => ['required', 'string', 'max:150'],
            'floor' => ['nullable', 'integer', 'between:-5,50'],
        ];
    }
}
```

- [ ] **Step 4: Controllers.** `CategoryController.php`

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveCategoryRequest;
use App\Models\Category;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Category::class);

        return Inertia::render('admin/categories/Index', [
            'categories' => Category::query()->with('defaultPic:id,name')->withCount('assets')->orderBy('name')->get()
                ->map(fn (Category $category) => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'default_pic_id' => $category->default_pic_id,
                    'default_pic_name' => $category->defaultPic?->name,
                    'assets_count' => $category->assets_count,
                ]),
            'pics' => User::query()->where('role', UserRole::Pic)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(SaveCategoryRequest $request): RedirectResponse
    {
        Gate::authorize('create', Category::class);

        Category::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kategori ditambahkan.']);

        return back();
    }

    public function update(SaveCategoryRequest $request, Category $category): RedirectResponse
    {
        Gate::authorize('update', $category);

        $category->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kategori diperbarui.']);

        return back();
    }

    public function destroy(Category $category): RedirectResponse
    {
        Gate::authorize('delete', $category);

        if ($category->isInUse()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Kategori masih dipakai dan tidak bisa dihapus.']);

            return back();
        }

        $category->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kategori dihapus.']);

        return back();
    }
}
```

`BuildingController.php`

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveBuildingRequest;
use App\Models\Building;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BuildingController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Building::class);

        return Inertia::render('admin/buildings/Index', [
            'buildings' => Building::query()->withCount('rooms')->orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    public function show(Building $building): Response
    {
        Gate::authorize('view', $building);

        return Inertia::render('admin/buildings/Show', [
            'building' => $building->only(['id', 'code', 'name']),
            'rooms' => $building->rooms()->orderBy('code')->get(['id', 'code', 'name', 'floor']),
        ]);
    }

    public function store(SaveBuildingRequest $request): RedirectResponse
    {
        Gate::authorize('create', Building::class);

        Building::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Gedung ditambahkan.']);

        return back();
    }

    public function update(SaveBuildingRequest $request, Building $building): RedirectResponse
    {
        Gate::authorize('update', $building);

        $building->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Gedung diperbarui.']);

        return back();
    }

    public function destroy(Building $building): RedirectResponse
    {
        Gate::authorize('delete', $building);

        if ($building->isInUse()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Gedung masih punya ruang. Hapus ruangnya dulu.']);

            return back();
        }

        $building->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Gedung dihapus.']);

        return to_route('admin.buildings.index');
    }
}
```

`RoomController.php`

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveRoomRequest;
use App\Models\Building;
use App\Models\Room;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class RoomController extends Controller
{
    public function store(SaveRoomRequest $request, Building $building): RedirectResponse
    {
        Gate::authorize('create', Room::class);

        $building->rooms()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Ruang ditambahkan.']);

        return back();
    }

    public function update(SaveRoomRequest $request, Room $room): RedirectResponse
    {
        Gate::authorize('update', $room);

        $room->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Ruang diperbarui.']);

        return back();
    }

    public function destroy(Room $room): RedirectResponse
    {
        Gate::authorize('delete', $room);

        if ($room->isInUse()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Ruang masih dipakai laporan dan tidak bisa dihapus.']);

            return back();
        }

        $room->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Ruang dihapus.']);

        return back();
    }
}
```

- [ ] **Step 5: Routes.** Add imports for the three controllers to `routes/web.php`, and inside the `role:admin` group:

```php
        Route::resource('categories', CategoryController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('buildings', BuildingController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
        Route::post('buildings/{building}/rooms', [RoomController::class, 'store'])->name('buildings.rooms.store');
        Route::put('rooms/{room}', [RoomController::class, 'update'])->name('rooms.update');
        Route::delete('rooms/{room}', [RoomController::class, 'destroy'])->name('rooms.destroy');
```

- [ ] **Step 6: Run the tests**

Before running, create stub pages `resources/js/pages/admin/categories/Index.vue`, `resources/js/pages/admin/buildings/Index.vue` and `resources/js/pages/admin/buildings/Show.vue`, each containing only `<template><div /></template>` (Inertia's `testing.ensure_pages_exist` is `true`). Step 7 replaces them.

Run: `php artisan test tests/Feature/Admin/CategoryTest.php tests/Feature/Admin/BuildingRoomTest.php`
Expected: all pass.

- [ ] **Step 7: Pages.** `resources/js/pages/admin/categories/Index.vue`

```vue
<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import EmptyState from '@/components/EmptyState.vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { destroy, index, store, update } from '@/routes/admin/categories';

type Row = { id: number; name: string; default_pic_id: number | null; default_pic_name: string | null; assets_count: number };

defineProps<{ categories: Row[]; pics: { id: number; name: string }[] }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Kategori', href: index() }] },
});

const open = ref(false);
const editing = ref<Row | null>(null);
const toDelete = ref<Row | null>(null);
const form = useForm({ name: '', default_pic_id: '' as string | number });

function create() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    open.value = true;
}

function edit(row: Row) {
    editing.value = row;
    form.name = row.name;
    form.default_pic_id = row.default_pic_id ?? '';
    form.clearErrors();
    open.value = true;
}

function save() {
    const options = { preserveScroll: true, onSuccess: () => (open.value = false) };

    if (editing.value) form.put(update.url(editing.value.id), options);
    else form.post(store.url(), options);
}

function remove() {
    if (!toDelete.value) return;
    router.delete(destroy.url(toDelete.value.id), { preserveScroll: true, onFinish: () => (toDelete.value = null) });
}
</script>

<template>
    <Head title="Kategori" />

    <div class="flex flex-col gap-4 p-4">
        <PageHeader title="Kategori" description="Kelompok aset dan PIC default yang disarankan saat validasi laporan.">
            <Button class="h-11" @click="create"><Plus class="size-4" /> Tambah kategori</Button>
        </PageHeader>

        <EmptyState v-if="categories.length === 0" title="Belum ada kategori" />

        <ul v-else class="divide-y rounded-xl border">
            <li v-for="row in categories" :key="row.id" class="flex items-center justify-between gap-3 p-3">
                <div class="min-w-0">
                    <p class="truncate font-medium">{{ row.name }}</p>
                    <p class="text-sm text-muted-foreground">
                        PIC: {{ row.default_pic_name ?? 'belum diatur' }} · {{ row.assets_count }} aset
                    </p>
                </div>
                <div class="flex shrink-0 gap-1">
                    <Button variant="outline" size="icon" class="size-11" :aria-label="`Ubah ${row.name}`" @click="edit(row)"><Pencil class="size-4" /></Button>
                    <Button variant="outline" size="icon" class="size-11" :aria-label="`Hapus ${row.name}`" @click="toDelete = row"><Trash2 class="size-4" /></Button>
                </div>
            </li>
        </ul>
    </div>

    <Dialog v-model:open="open">
        <DialogContent>
            <form class="grid gap-4" @submit.prevent="save">
                <DialogHeader><DialogTitle>{{ editing ? 'Ubah kategori' : 'Tambah kategori' }}</DialogTitle></DialogHeader>
                <div class="grid gap-2">
                    <Label for="cat-name">Nama kategori</Label>
                    <Input id="cat-name" v-model="form.name" class="h-11" required />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="grid gap-2">
                    <Label for="cat-pic">PIC default</Label>
                    <NativeSelect id="cat-pic" v-model="form.default_pic_id">
                        <option value="">Belum diatur</option>
                        <option v-for="pic in pics" :key="pic.id" :value="pic.id">{{ pic.name }}</option>
                    </NativeSelect>
                    <InputError :message="form.errors.default_pic_id" />
                </div>
                <DialogFooter>
                    <Button type="button" variant="outline" class="h-11" @click="open = false">Batal</Button>
                    <Button type="submit" class="h-11" :disabled="form.processing">Simpan</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <ConfirmDialog
        :open="toDelete !== null"
        title="Hapus kategori?"
        :description="`Kategori ${toDelete?.name} akan dihapus.`"
        confirm-label="Hapus"
        destructive
        @update:open="(value) => !value && (toDelete = null)"
        @confirm="remove"
    />
</template>
```

`resources/js/pages/admin/buildings/Index.vue`

```vue
<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import EmptyState from '@/components/EmptyState.vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { destroy, index, show, store, update } from '@/routes/admin/buildings';

type Row = { id: number; code: string; name: string; rooms_count: number };

defineProps<{ buildings: Row[] }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Gedung & Ruang', href: index() }] },
});

const open = ref(false);
const editing = ref<Row | null>(null);
const toDelete = ref<Row | null>(null);
const form = useForm({ code: '', name: '' });

function create() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    open.value = true;
}

function edit(row: Row) {
    editing.value = row;
    form.code = row.code;
    form.name = row.name;
    form.clearErrors();
    open.value = true;
}

function save() {
    const options = { preserveScroll: true, onSuccess: () => (open.value = false) };

    if (editing.value) form.put(update.url(editing.value.id), options);
    else form.post(store.url(), options);
}

function remove() {
    if (!toDelete.value) return;
    router.delete(destroy.url(toDelete.value.id), { preserveScroll: true, onFinish: () => (toDelete.value = null) });
}
</script>

<template>
    <Head title="Gedung & Ruang" />

    <div class="flex flex-col gap-4 p-4">
        <PageHeader title="Gedung & Ruang" description="Lokasi kerusakan dipilih pelapor dari daftar ini.">
            <Button class="h-11" @click="create"><Plus class="size-4" /> Tambah gedung</Button>
        </PageHeader>

        <EmptyState v-if="buildings.length === 0" title="Belum ada gedung" />

        <ul v-else class="divide-y rounded-xl border">
            <li v-for="row in buildings" :key="row.id" class="flex items-center justify-between gap-3 p-3">
                <Link :href="show.url(row.id)" class="min-w-0 flex-1 rounded focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none">
                    <p class="truncate font-medium">{{ row.code }} · {{ row.name }}</p>
                    <p class="text-sm text-muted-foreground">{{ row.rooms_count }} ruang</p>
                </Link>
                <div class="flex shrink-0 gap-1">
                    <Button variant="outline" size="icon" class="size-11" :aria-label="`Ubah ${row.name}`" @click="edit(row)"><Pencil class="size-4" /></Button>
                    <Button variant="outline" size="icon" class="size-11" :aria-label="`Hapus ${row.name}`" @click="toDelete = row"><Trash2 class="size-4" /></Button>
                </div>
            </li>
        </ul>
    </div>

    <Dialog v-model:open="open">
        <DialogContent>
            <form class="grid gap-4" @submit.prevent="save">
                <DialogHeader><DialogTitle>{{ editing ? 'Ubah gedung' : 'Tambah gedung' }}</DialogTitle></DialogHeader>
                <div class="grid gap-2">
                    <Label for="b-code">Kode</Label>
                    <Input id="b-code" v-model="form.code" class="h-11" required />
                    <InputError :message="form.errors.code" />
                </div>
                <div class="grid gap-2">
                    <Label for="b-name">Nama gedung</Label>
                    <Input id="b-name" v-model="form.name" class="h-11" required />
                    <InputError :message="form.errors.name" />
                </div>
                <DialogFooter>
                    <Button type="button" variant="outline" class="h-11" @click="open = false">Batal</Button>
                    <Button type="submit" class="h-11" :disabled="form.processing">Simpan</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <ConfirmDialog
        :open="toDelete !== null"
        title="Hapus gedung?"
        :description="`Gedung ${toDelete?.name} akan dihapus.`"
        confirm-label="Hapus"
        destructive
        @update:open="(value) => !value && (toDelete = null)"
        @confirm="remove"
    />
</template>
```

`resources/js/pages/admin/buildings/Show.vue`

```vue
<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import EmptyState from '@/components/EmptyState.vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/admin/buildings';
import { store as storeRoom } from '@/routes/admin/buildings/rooms';
import { destroy as destroyRoom, update as updateRoom } from '@/routes/admin/rooms';

type Room = { id: number; code: string; name: string; floor: number | null };

const props = defineProps<{ building: { id: number; code: string; name: string }; rooms: Room[] }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Gedung & Ruang', href: index() }] },
});

const open = ref(false);
const editing = ref<Room | null>(null);
const toDelete = ref<Room | null>(null);
const form = useForm({ code: '', name: '', floor: '' as string | number });

function create() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    open.value = true;
}

function edit(room: Room) {
    editing.value = room;
    form.code = room.code;
    form.name = room.name;
    form.floor = room.floor ?? '';
    form.clearErrors();
    open.value = true;
}

function save() {
    const options = { preserveScroll: true, onSuccess: () => (open.value = false) };

    if (editing.value) form.put(updateRoom.url(editing.value.id), options);
    else form.post(storeRoom.url(props.building.id), options);
}

function remove() {
    if (!toDelete.value) return;
    router.delete(destroyRoom.url(toDelete.value.id), { preserveScroll: true, onFinish: () => (toDelete.value = null) });
}
</script>

<template>
    <Head :title="`Ruang ${building.name}`" />

    <div class="flex flex-col gap-4 p-4">
        <PageHeader :title="`${building.code} · ${building.name}`" description="Daftar ruang di gedung ini.">
            <Button class="h-11" @click="create"><Plus class="size-4" /> Tambah ruang</Button>
        </PageHeader>

        <EmptyState v-if="rooms.length === 0" title="Belum ada ruang" description="Tambahkan ruang agar bisa dipilih saat melapor." />

        <ul v-else class="divide-y rounded-xl border">
            <li v-for="room in rooms" :key="room.id" class="flex items-center justify-between gap-3 p-3">
                <div class="min-w-0">
                    <p class="truncate font-medium">{{ room.code }} · {{ room.name }}</p>
                    <p class="text-sm text-muted-foreground">Lantai {{ room.floor ?? '-' }}</p>
                </div>
                <div class="flex shrink-0 gap-1">
                    <Button variant="outline" size="icon" class="size-11" :aria-label="`Ubah ${room.name}`" @click="edit(room)"><Pencil class="size-4" /></Button>
                    <Button variant="outline" size="icon" class="size-11" :aria-label="`Hapus ${room.name}`" @click="toDelete = room"><Trash2 class="size-4" /></Button>
                </div>
            </li>
        </ul>
    </div>

    <Dialog v-model:open="open">
        <DialogContent>
            <form class="grid gap-4" @submit.prevent="save">
                <DialogHeader><DialogTitle>{{ editing ? 'Ubah ruang' : 'Tambah ruang' }}</DialogTitle></DialogHeader>
                <div class="grid gap-2">
                    <Label for="r-code">Kode ruang</Label>
                    <Input id="r-code" v-model="form.code" class="h-11" required />
                    <InputError :message="form.errors.code" />
                </div>
                <div class="grid gap-2">
                    <Label for="r-name">Nama ruang</Label>
                    <Input id="r-name" v-model="form.name" class="h-11" required />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="grid gap-2">
                    <Label for="r-floor">Lantai (opsional)</Label>
                    <Input id="r-floor" v-model="form.floor" type="number" class="h-11" />
                    <InputError :message="form.errors.floor" />
                </div>
                <DialogFooter>
                    <Button type="button" variant="outline" class="h-11" @click="open = false">Batal</Button>
                    <Button type="submit" class="h-11" :disabled="form.processing">Simpan</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <ConfirmDialog
        :open="toDelete !== null"
        title="Hapus ruang?"
        :description="`Ruang ${toDelete?.name} akan dihapus.`"
        confirm-label="Hapus"
        destructive
        @update:open="(value) => !value && (toDelete = null)"
        @confirm="remove"
    />
</template>
```

- [ ] **Step 8: Nav entries.** In `useRoleNav.ts` import `Building2, Tags` from `@lucide/vue`, `index as categoriesIndex` from `@/routes/admin/categories`, `index as buildingsIndex` from `@/routes/admin/buildings`, and append to `adminNav`:

```ts
    { title: 'Kategori', href: categoriesIndex(), icon: Tags },
    { title: 'Gedung & Ruang', href: buildingsIndex(), icon: Building2 },
```

- [ ] **Step 9: Verify**

Run: `php artisan test && php artisan wayfinder:generate && npm run types:check && npm run check`
Expected: all pass. If the Wayfinder import `@/routes/admin/buildings/rooms` does not exist, run `ls resources/js/routes/admin/buildings` after generation and use the generated module path for `admin.buildings.rooms.store`.

- [ ] **Step 10: Commit**

```bash
git add -A
git commit -m "feat: admin CRUD for categories (with default PIC), buildings and rooms"
```

---

### Task 6: Users CRUD and user import

**Files:**
- Create: `app/Policies/UserPolicy.php`, `app/Http/Requests/Admin/{SaveUserRequest,ImportUsersRequest}.php`, `app/Actions/Users/ImportUsers.php`, `app/Http/Controllers/Admin/{UserController,UserImportController}.php`, `resources/js/pages/admin/users/Index.vue`, `tests/Feature/Admin/{UserManagementTest,UserImportTest}.php`
- Modify: `routes/web.php`, `resources/js/composables/useRoleNav.ts`

**Interfaces:**
- Produces routes: `admin.users.{index,store,update}`, `admin.users.active` (`PATCH admin/users/{user}/active`), `admin.users.password` (`PUT admin/users/{user}/password`), `admin.users.import` (`POST admin/users/import`).
- Produces `ImportUsers::handle(string $path, string $extension): array{created:int,updated:int,failed:int,errors:list<array{line:int,message:string}>,generated:list<array{nim_nip:string,password:string}>}`. File columns: `nim_nip, name, email, role, password` (header row required; `password` optional → generated for new users and shown once).

- [ ] **Step 1: Write the failing tests.** `tests/Feature/Admin/UserManagementTest.php`

```php
<?php

use App\Enums\UserRole;
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
```

`tests/Feature/Admin/UserImportTest.php`

```php
<?php

use App\Enums\UserRole;
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

    $this->actingAs($this->admin)->post(route('admin.users.import'), ['file' => usersCsv([['5001', 'Nama Baru', '', 'user', '']])]);

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
```

- [ ] **Step 2: Run to verify they fail**

Run: `php artisan test tests/Feature/Admin/UserManagementTest.php tests/Feature/Admin/UserImportTest.php`
Expected: FAIL (`Route [admin.users.index] not defined`).

- [ ] **Step 3: Policy** `app/Policies/UserPolicy.php`

```php
<?php

namespace App\Policies;

use App\Policies\Concerns\AdminOnly;

class UserPolicy
{
    use AdminOnly;
}
```

- [ ] **Step 4: Requests.** `SaveUserRequest.php` (create when `user` route param is absent, update otherwise)

```php
<?php

namespace App\Http\Requests\Admin;

use App\Concerns\ProfileValidationRules;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveUserRequest extends FormRequest
{
    use ProfileValidationRules;

    public function rules(): array
    {
        $user = $this->route('user');
        $id = $user instanceof User ? $user->id : null;

        return [
            'nim_nip' => $this->nimNipRules($id),
            'name' => $this->nameRules(),
            'email' => $this->emailRules($id),
            'role' => ['required', Rule::enum(UserRole::class)],
            'password' => [$id === null ? 'required' : 'nullable', 'string', 'min:8', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return ['nim_nip.unique' => 'NIM/NIP sudah terdaftar.'];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $user = $this->route('user');

            if ($user instanceof User && $user->is($this->user()) && $this->input('role') !== UserRole::Admin->value) {
                $validator->errors()->add('role', 'Anda tidak bisa mengubah peran akun sendiri.');
            }
        }];
    }
}
```

`ImportUsersRequest.php`

```php
<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ImportUsersRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx,csv,txt', 'max:5120'],
        ];
    }
}
```

- [ ] **Step 5: `ImportUsers` action** `app/Actions/Users/ImportUsers.php`

```php
<?php

namespace App\Actions\Users;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\Bmn\BmnValue;
use App\Support\Spreadsheet\RowReader;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class ImportUsers
{
    /** @return array{created: int, updated: int, failed: int, errors: list<array{line: int, message: string}>, generated: list<array{nim_nip: string, password: string}>} */
    public function handle(string $path, string $extension): array
    {
        $result = ['created' => 0, 'updated' => 0, 'failed' => 0, 'errors' => [], 'generated' => []];

        foreach (RowReader::assoc($path, $extension) as $line => $row) {
            $data = [
                'nim_nip' => BmnValue::string($row['nim_nip'] ?? null),
                'name' => BmnValue::string($row['name'] ?? null),
                'email' => BmnValue::string($row['email'] ?? null),
                'role' => strtolower((string) BmnValue::string($row['role'] ?? null)),
                'password' => BmnValue::string($row['password'] ?? null),
            ];

            if (! array_filter($data)) {
                continue; // blank line
            }

            $existing = $data['nim_nip'] === null ? null : User::where('nim_nip', $data['nim_nip'])->first();

            $validator = Validator::make($data, [
                'nim_nip' => ['required', 'string', 'max:255'],
                'name' => ['required', 'string', 'max:255'],
                'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($existing?->id)],
                'role' => ['required', Rule::enum(UserRole::class)],
                'password' => ['nullable', 'string', 'min:8', 'max:255'],
            ], [], ['role' => 'peran']);

            if ($validator->fails()) {
                $result['failed']++;
                $result['errors'][] = ['line' => $line, 'message' => $validator->errors()->first()];

                continue;
            }

            if ($existing) {
                $existing->update(array_filter([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'role' => $data['role'],
                    'password' => $data['password'],
                ], fn ($value) => $value !== null));
                $result['updated']++;

                continue;
            }

            $password = $data['password'] ?? Str::password(10, symbols: false);

            User::create([
                'nim_nip' => $data['nim_nip'],
                'name' => $data['name'],
                'email' => $data['email'],
                'role' => $data['role'],
                'password' => $password,
                'is_active' => true,
            ]);

            $result['created']++;

            if ($data['password'] === null) {
                $result['generated'][] = ['nim_nip' => $data['nim_nip'], 'password' => $password];
            }
        }

        return $result;
    }
}
```

- [ ] **Step 6: Controllers.** `UserController.php`

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', 'in:user,admin,pic'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);

        $users = User::query()
            ->when($filters['q'] ?? null, function ($query, string $q) {
                $like = '%'.addcslashes($q, '%_\\').'%';
                $query->where(fn ($inner) => $inner->where('name', 'like', $like)->orWhere('nim_nip', 'like', $like));
            })
            ->when($filters['role'] ?? null, fn ($query, $role) => $query->where('role', $role))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('is_active', $status === 'active'))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (User $user) => [
                'id' => $user->id,
                'nim_nip' => $user->nim_nip,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
                'role_label' => $user->role->label(),
                'is_active' => $user->is_active,
            ]);

        return Inertia::render('admin/users/Index', [
            'users' => $users,
            'filters' => $filters,
            'roles' => collect(UserRole::cases())->map(fn (UserRole $role) => ['value' => $role->value, 'label' => $role->label()]),
            'importResult' => session('import_result'),
        ]);
    }

    public function store(SaveUserRequest $request): RedirectResponse
    {
        Gate::authorize('create', User::class);

        User::create([...$request->validated(), 'is_active' => true]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pengguna ditambahkan.']);

        return back();
    }

    public function update(SaveUserRequest $request, User $user): RedirectResponse
    {
        Gate::authorize('update', $user);

        $data = $request->validated();

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $user->update($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pengguna diperbarui.']);

        return back();
    }

    public function active(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('update', $user);

        $data = $request->validate(['is_active' => ['required', 'boolean']]);

        if ($user->is($request->user()) && ! $data['is_active']) {
            return back()->withErrors(['user' => 'Anda tidak bisa menonaktifkan akun sendiri.']);
        }

        $user->update(['is_active' => $data['is_active']]);

        Inertia::flash('toast', ['type' => 'success', 'message' => $data['is_active'] ? 'Akun diaktifkan.' : 'Akun dinonaktifkan.']);

        return back();
    }

    public function password(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('update', $user);

        $data = $request->validate(['password' => ['required', 'string', 'min:8', 'max:255']]);

        $user->update(['password' => $data['password']]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Password diganti.']);

        return back();
    }
}
```

`UserImportController.php`

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Users\ImportUsers;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImportUsersRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class UserImportController extends Controller
{
    public function store(ImportUsersRequest $request, ImportUsers $importer): RedirectResponse
    {
        Gate::authorize('create', User::class);

        $file = $request->file('file');

        $result = $importer->handle($file->getRealPath(), $file->getClientOriginalExtension());

        return to_route('admin.users.index')->with('import_result', $result);
    }
}
```

Note: `ImportUsersRequest` runs before `Gate::authorize`; a non-admin posting an empty body therefore gets 403 only if validation passes. Because the route sits inside `role:admin`, the middleware already returns 403 first.

- [ ] **Step 7: Routes.** Add imports for `UserController` and `UserImportController`, and inside the `role:admin` group (register `users/import` **before** the `{user}` routes):

```php
        Route::post('users/import', [UserImportController::class, 'store'])->name('users.import');
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::patch('users/{user}/active', [UserController::class, 'active'])->name('users.active');
        Route::put('users/{user}/password', [UserController::class, 'password'])->name('users.password');
```

- [ ] **Step 8: Run the tests**

Before running, create the stub page `resources/js/pages/admin/users/Index.vue` containing only `<template><div /></template>`. Step 9 replaces it.

Run: `php artisan test tests/Feature/Admin/UserManagementTest.php tests/Feature/Admin/UserImportTest.php`
Expected: all pass.

- [ ] **Step 9: Users page** `resources/js/pages/admin/users/Index.vue`

```vue
<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { KeyRound, Pencil, Plus, Power, Upload } from '@lucide/vue';
import { computed, ref } from 'vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import EmptyState from '@/components/EmptyState.vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
// Wayfinder renames the reserved word `import` to `importMethod`.
import { active, importMethod as importUsers, index, password as setPassword, store, update } from '@/routes/admin/users';

type Row = { id: number; nim_nip: string; name: string; email: string | null; role: string; role_label: string; is_active: boolean };
type ImportResult = {
    created: number; updated: number; failed: number;
    errors: { line: number; message: string }[];
    generated: { nim_nip: string; password: string }[];
};

const props = defineProps<{
    users: { data: Row[]; links: { url: string | null; label: string; active: boolean }[] };
    filters: { q?: string; role?: string; status?: string };
    roles: { value: string; label: string }[];
    importResult: ImportResult | null;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Pengguna', href: index() }] },
});

const me = computed(() => usePage().props.auth.user.id);
const q = ref(props.filters.q ?? '');
const role = ref(props.filters.role ?? '');
const status = ref(props.filters.status ?? '');

function applyFilters() {
    router.get(index.url(), { q: q.value || undefined, role: role.value || undefined, status: status.value || undefined }, { preserveState: true, replace: true });
}

const formOpen = ref(false);
const editing = ref<Row | null>(null);
const form = useForm({ nim_nip: '', name: '', email: '', role: 'user', password: '' });

function create() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    formOpen.value = true;
}

function edit(row: Row) {
    editing.value = row;
    form.nim_nip = row.nim_nip;
    form.name = row.name;
    form.email = row.email ?? '';
    form.role = row.role;
    form.password = '';
    form.clearErrors();
    formOpen.value = true;
}

function save() {
    const options = { preserveScroll: true, onSuccess: () => (formOpen.value = false) };

    if (editing.value) form.put(update.url(editing.value.id), options);
    else form.post(store.url(), options);
}

const pwUser = ref<Row | null>(null);
const pwForm = useForm({ password: '' });

function savePassword() {
    if (!pwUser.value) return;
    pwForm.put(setPassword.url(pwUser.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            pwUser.value = null;
            pwForm.reset();
        },
    });
}

const toggling = ref<Row | null>(null);

function confirmToggle() {
    if (!toggling.value) return;
    router.patch(active.url(toggling.value.id), { is_active: !toggling.value.is_active }, { preserveScroll: true, onFinish: () => (toggling.value = null) });
}

const importForm = useForm<{ file: File | null }>({ file: null });

function submitImport() {
    importForm.post(importUsers.url(), { forceFormData: true, onSuccess: () => importForm.reset() });
}
</script>

<template>
    <Head title="Pengguna" />

    <div class="flex flex-col gap-4 p-4">
        <PageHeader title="Pengguna" description="Akun dibuat dan dikelola admin. Akun tidak dihapus, hanya dinonaktifkan.">
            <Button class="h-11" @click="create"><Plus class="size-4" /> Tambah pengguna</Button>
        </PageHeader>

        <form class="grid gap-2 md:grid-cols-4" @submit.prevent="applyFilters">
            <Input v-model="q" class="h-11 md:col-span-2" placeholder="Cari nama atau NIM/NIP" aria-label="Cari pengguna" />
            <NativeSelect v-model="role" aria-label="Peran">
                <option value="">Semua peran</option>
                <option v-for="r in roles" :key="r.value" :value="r.value">{{ r.label }}</option>
            </NativeSelect>
            <NativeSelect v-model="status" aria-label="Status">
                <option value="">Semua status</option>
                <option value="active">Aktif</option>
                <option value="inactive">Nonaktif</option>
            </NativeSelect>
            <Button type="submit" class="h-11 md:col-span-4 md:w-fit">Terapkan filter</Button>
        </form>

        <section v-if="importResult" class="rounded-xl border border-primary/40 bg-secondary p-4 text-sm" role="status">
            <p class="font-semibold">
                Impor selesai: {{ importResult.created }} dibuat, {{ importResult.updated }} diperbarui, {{ importResult.failed }} gagal.
            </p>
            <ul v-if="importResult.errors.length" class="mt-2 list-disc pl-5 text-destructive">
                <li v-for="(error, i) in importResult.errors" :key="i">Baris {{ error.line }}: {{ error.message }}</li>
            </ul>
            <div v-if="importResult.generated.length" class="mt-3">
                <p class="font-medium">Password awal (hanya tampil sekali, catat sekarang):</p>
                <ul class="mt-1 grid gap-1 font-mono">
                    <li v-for="g in importResult.generated" :key="g.nim_nip">{{ g.nim_nip }} → {{ g.password }}</li>
                </ul>
            </div>
        </section>

        <EmptyState v-if="users.data.length === 0" title="Pengguna tidak ditemukan" description="Ubah filter atau tambah pengguna baru." />

        <ul v-else class="divide-y rounded-xl border">
            <li v-for="row in users.data" :key="row.id" class="flex flex-col gap-2 p-3 md:flex-row md:items-center md:justify-between">
                <div class="min-w-0">
                    <p class="truncate font-medium">
                        {{ row.name }}
                        <span v-if="!row.is_active" class="ml-1 rounded bg-status-cancelled-bg px-2 py-0.5 text-xs font-semibold text-status-cancelled">Nonaktif</span>
                    </p>
                    <p class="text-sm text-muted-foreground">{{ row.nim_nip }} · {{ row.role_label }} · {{ row.email ?? 'tanpa email' }}</p>
                </div>
                <div class="flex shrink-0 gap-1">
                    <Button variant="outline" size="icon" class="size-11" :aria-label="`Ubah ${row.name}`" @click="edit(row)"><Pencil class="size-4" /></Button>
                    <Button variant="outline" size="icon" class="size-11" :aria-label="`Ganti password ${row.name}`" @click="pwUser = row"><KeyRound class="size-4" /></Button>
                    <Button
                        variant="outline"
                        size="icon"
                        class="size-11"
                        :disabled="row.id === me"
                        :aria-label="row.is_active ? `Nonaktifkan ${row.name}` : `Aktifkan ${row.name}`"
                        @click="toggling = row"
                    >
                        <Power class="size-4" />
                    </Button>
                </div>
            </li>
        </ul>

        <Pagination :links="users.links" />

        <section class="rounded-xl border p-4">
            <h2 class="font-semibold">Impor pengguna</h2>
            <p class="text-sm text-muted-foreground">
                File .xlsx atau .csv dengan kolom <code>nim_nip, name, email, role, password</code> (role: user, admin, pic).
                Password kosong akan dibuat otomatis untuk pengguna baru.
            </p>
            <form class="mt-3 grid gap-2 sm:max-w-xl" @submit.prevent="submitImport">
                <Input type="file" accept=".xlsx,.csv,.txt" class="h-11" aria-label="Berkas impor pengguna" @input="importForm.file = ($event.target as HTMLInputElement).files?.[0] ?? null" />
                <InputError :message="importForm.errors.file" />
                <Button type="submit" class="h-11 w-fit" :disabled="importForm.processing || !importForm.file"><Upload class="size-4" /> Impor</Button>
            </form>
        </section>
    </div>

    <Dialog v-model:open="formOpen">
        <DialogContent>
            <form class="grid gap-4" @submit.prevent="save">
                <DialogHeader><DialogTitle>{{ editing ? 'Ubah pengguna' : 'Tambah pengguna' }}</DialogTitle></DialogHeader>
                <div class="grid gap-2">
                    <Label for="u-nim">NIM/NIP</Label>
                    <Input id="u-nim" v-model="form.nim_nip" class="h-11" required />
                    <InputError :message="form.errors.nim_nip" />
                </div>
                <div class="grid gap-2">
                    <Label for="u-name">Nama</Label>
                    <Input id="u-name" v-model="form.name" class="h-11" required />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="grid gap-2">
                    <Label for="u-email">Email (opsional)</Label>
                    <Input id="u-email" v-model="form.email" type="email" class="h-11" />
                    <InputError :message="form.errors.email" />
                </div>
                <div class="grid gap-2">
                    <Label for="u-role">Peran</Label>
                    <NativeSelect id="u-role" v-model="form.role">
                        <option v-for="r in roles" :key="r.value" :value="r.value">{{ r.label }}</option>
                    </NativeSelect>
                    <InputError :message="form.errors.role" />
                </div>
                <div v-if="!editing" class="grid gap-2">
                    <Label for="u-pass">Password awal</Label>
                    <Input id="u-pass" v-model="form.password" type="text" class="h-11" required minlength="8" />
                    <InputError :message="form.errors.password" />
                </div>
                <DialogFooter>
                    <Button type="button" variant="outline" class="h-11" @click="formOpen = false">Batal</Button>
                    <Button type="submit" class="h-11" :disabled="form.processing">Simpan</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <Dialog :open="pwUser !== null" @update:open="(v) => !v && (pwUser = null)">
        <DialogContent>
            <form class="grid gap-4" @submit.prevent="savePassword">
                <DialogHeader><DialogTitle>Ganti password {{ pwUser?.name }}</DialogTitle></DialogHeader>
                <div class="grid gap-2">
                    <Label for="pw-new">Password baru</Label>
                    <Input id="pw-new" v-model="pwForm.password" type="text" class="h-11" required minlength="8" />
                    <InputError :message="pwForm.errors.password" />
                </div>
                <DialogFooter>
                    <Button type="button" variant="outline" class="h-11" @click="pwUser = null">Batal</Button>
                    <Button type="submit" class="h-11" :disabled="pwForm.processing">Simpan</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <ConfirmDialog
        :open="toggling !== null"
        :title="toggling?.is_active ? 'Nonaktifkan akun?' : 'Aktifkan akun?'"
        :description="toggling?.is_active ? `${toggling?.name} tidak akan bisa masuk sampai diaktifkan lagi.` : `${toggling?.name} bisa masuk kembali.`"
        :confirm-label="toggling?.is_active ? 'Nonaktifkan' : 'Aktifkan'"
        :destructive="toggling?.is_active"
        @update:open="(value) => !value && (toggling = null)"
        @confirm="confirmToggle"
    />
</template>
```

- [ ] **Step 10: Nav entry.** In `useRoleNav.ts` import `Users` from `@lucide/vue` and `index as usersIndex` from `@/routes/admin/users`; append to `adminNav`:

```ts
    { title: 'Pengguna', href: usersIndex(), icon: Users },
```

- [ ] **Step 11: Verify everything**

Run: `php artisan test && composer lint:check && composer types:check && php artisan wayfinder:generate && npm run types:check && npm run check && npm run build`
Expected: all pass.

- [ ] **Step 12: Commit**

```bash
git add -A
git commit -m "feat: admin user management and bulk user import"
```

---

## Self-Review (Plan 2)

- **Spec coverage:** F02 AC1 (duplicate NIM/NIP) → Task 6; AC2 (deactivated cannot log in) → Plan 1 `AuthenticationTest` + Task 6 toggle; AC3 (7 created/2 updated/1 failed with row and reason) → Task 6 `UserImportTest`; AC4 (only `pic`) → Task 5 `CategoryTest`; AC5 (category in use) → Task 5; AC6 (rooms per building) → Plan 3 (rooms delivered to the form as props); AC7 → 403 tests in Tasks 4–6. F03 AC1 (25,995 / 16) → Task 3 Step 6; AC2 separator skipped → Task 3 test 1; AC3 dates → Tasks 2–3; AC4 idempotent upsert → Task 3; AC5 UI import + status → Task 4; AC6 non-xlsx → Task 4; AC7 search ≤ 20 → Task 4 (timing is a manual check against the 26k-row table); AC8 read-only → Task 4 (`405` on write attempt).
- **Placeholder scan:** none; `BuildingSeeder` is explicitly placeholder *data* as specified.
- **Type consistency:** `Category::isInUse()`, `Building::isInUse()`, `Room::isInUse()` are the hooks Plan 3 extends; `AdminOnly` trait and `NativeSelect` come from Plan 1; `ImportAssets::handle` signature matches the job and command.
- **Review Focus:** all five lines are covered (Task 2 numeric codes, Task 3 idempotency and empty Kelompok, Task 6 email clash and self-protection).
- **Deviation:** uses `openspout/openspout` instead of `maatwebsite/excel` (streaming, no PhpSpreadsheet memory cost); Plan 5 reuses it for the Excel export.
