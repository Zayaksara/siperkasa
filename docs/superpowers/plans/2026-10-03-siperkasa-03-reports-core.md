# SiPerkasa Plan 3 — Reports Core (Create, My Reports, Notifications Backend)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A reporter creates a damage report from a phone (with 1–3 compressed photos), follows it through a timeline, and can edit or cancel it while `Pending`. The state machine, policy, ticket numbers and notification classes that every later feature relies on are built and tested here.

**Architecture:** `ReportStatus` enum is the single source of truth for transitions. `ReportTransitioner` is the only code that changes `status` and writes `report_status_logs`. Single-purpose Action classes wrap each use case. `ReportPolicy` encodes who may do what in which status. Photos live on the private `local` disk and are served through an authorized route.

**Tech Stack:** Laravel 13, Pest 5, Inertia 3 + Vue 3 + TypeScript, MySQL 8.

**Spec:** `C:\Users\Lenovo\Documents\Obsidian Vault\40 - PROJECTS\SiPerkasa\04-Spec.md` (also `03-Tasks/F04-Buat-Laporan.md`, `F05-Laporan-Saya.md`, `F08-Notifikasi.md`, `D:\siperkasa\.agents\rules\{siperkasa,db}.md`). Requires Plans 1 and 2.

## Global Constraints

- Statuses: `Pending, Assigned, In_Progress, On_Hold, Closed, Rejected, Cancelled`. Final: `Closed, Rejected, Cancelled`. No reopen.
- Valid transitions: `Pending→{Assigned,Rejected,Cancelled}`, `Assigned→In_Progress`, `In_Progress→{On_Hold,Closed}`, `On_Hold→In_Progress`.
- Ticket format `SPK-YYMM-NNNN`, resets each month, created inside a transaction with a row lock.
- Report fields: `title` ≤ 120 chars, `description` 10–2000 chars, `category_id` and `room_id` required, `asset_id` optional, `user_priority` ∈ `Low|Medium|High|Critical`.
- Photos: 1–3 per stage, `jpg|jpeg|png|webp`, ≤ 2048 KB each, client compresses to ≤ 1600 px / quality 0.8; private disk `local`; path `reports/{report_id}/{type}/…`; served only through `GET /attachments/{attachment}` after `ReportPolicy::view`.
- Edit and cancel: only the owner and only while `Pending`.
- Every status change and every edit writes a `report_status_logs` row (`from_status`, `to_status`, `actor_id`, `note`).
- Notification channel: `database` only; payload keys `report_id`, `ticket_number`, `status`, `message`, `url`. Messages are Indonesian.
- Paginated props use `->paginate()->through(fn …)`; reports are presented through `App\Support\ReportPresenter`.
- UI: Bahasa Indonesia, tokens only, touch targets ≥ 44 px, `StatusBadge` shows text. The sticky submit bar sits above the mobile bottom nav (`bottom-14 md:bottom-0`).
- Commit messages must not contain any Claude attribution or `Co-Authored-By` trailer.

## Review Focus

- A title of exactly 120 multibyte characters (e.g. `é`) is accepted; 121 is rejected (Task 5).
- Exactly 3 photos and a photo of exactly 2048 KB are accepted; 4 photos and 2049 KB are rejected (Task 5).
- Five reports created in a row in the same month get unique, increasing tickets, and the first report of the next month is `0001` (Task 2).
- Editing or cancelling a report whose status changed after the form was opened (stale page) is refused with 403 and changes nothing (Task 6).
- Removing every existing photo while adding none is refused; the report keeps its photo (Task 6).

## File Structure

| File | Responsibility |
|---|---|
| `app/Enums/{ReportStatus,Priority,AttachmentType}.php` | Status machine, priority + SLA days, attachment type |
| `config/siperkasa.php` | SLA days, photo limits |
| `app/Models/{Report,ReportAttachment,ReportStatusLog}.php` | Domain models, `visibleTo`/`overdue` scopes |
| `app/Support/TicketNumber.php` | Monthly ticket generator |
| `app/Actions/Reports/ReportTransitioner.php`, `InvalidTransition.php` | The only place status changes |
| `app/Actions/Reports/AttachmentStore.php` | Save/remove photos |
| `app/Actions/Reports/{SubmitReport,UpdateReport,CancelReport}.php` | Use cases of this plan |
| `app/Notifications/{ReportSubmitted,ReportAssigned,ReportStatusChanged}.php`, `app/Support/ReportUrl.php` | Notification classes (sent by Plans 3–4) |
| `app/Policies/ReportPolicy.php` | All report abilities (used by Plans 3–4) |
| `app/Support/ReportPresenter.php` | Report → Inertia props (used by Plans 3–5) |
| `resources/js/components/{PhotoUploader,AssetCombobox,ReportForm,Timeline}.vue`, `resources/js/lib/compressImage.ts` | Reporter UI building blocks |

---

### Task 1: Enums, config, schema, models, factories

**Files:**
- Create: `app/Enums/{ReportStatus,Priority,AttachmentType}.php`, `config/siperkasa.php`, `database/migrations/2026_09_28_100010_create_report_attachments_table.php`, `…100011_create_report_status_logs_table.php`, `app/Models/{ReportAttachment,ReportStatusLog}.php`, `database/factories/{Report,ReportAttachment}Factory.php`, `tests/Unit/ReportStatusTest.php`, `tests/Feature/Reports/{PriorityTest,ReportModelTest}.php`
- Modify: `database/migrations/2026_09_28_100002_create_reports_table.php`, `app/Models/{Report,Category,Room}.php`, `routes/web.php`

**Interfaces (all later plans rely on these exactly):**
- `ReportStatus` cases `Pending, Assigned, InProgress ('In_Progress'), OnHold ('On_Hold'), Closed, Rejected, Cancelled`; `label(): string`; `allowedNext(): list<self>`; `canTransitionTo(self): bool`; `isFinal(): bool`; `static finalValues(): list<string>`.
- `Priority` cases `Low, Medium, High, Critical`; `label(): string`; `slaDays(): int`.
- `AttachmentType` `Damage='damage'`, `Completion='completion'`.
- `Report` casts `status` → `ReportStatus`, `user_priority`/`admin_priority` → `Priority`; relations `user, category, room, asset, admin, assignedPic, attachments, damagePhotos, completionPhotos, statusLogs`; `scopeVisibleTo(User)`, `scopeOverdue()`, `isOverdue(): bool`.
- `ReportFactory` states: `pending()` (default), `assigned(?User $pic = null)`, `inProgress()`, `onHold()`, `closed()`, `rejected()`, `cancelled()`.

- [ ] **Step 1: Write the failing unit test** `tests/Unit/ReportStatusTest.php`

```php
<?php

use App\Enums\ReportStatus as S;

$valid = [
    [S::Pending, S::Assigned],
    [S::Pending, S::Rejected],
    [S::Pending, S::Cancelled],
    [S::Assigned, S::InProgress],
    [S::InProgress, S::OnHold],
    [S::InProgress, S::Closed],
    [S::OnHold, S::InProgress],
];

it('allows every documented transition', function (S $from, S $to) {
    expect($from->canTransitionTo($to))->toBeTrue();
})->with($valid);

it('rejects every other pair, including staying in the same status', function () use ($valid) {
    $allowed = array_map(fn ($pair) => $pair[0]->value.'>'.$pair[1]->value, $valid);

    foreach (S::cases() as $from) {
        foreach (S::cases() as $to) {
            if (in_array($from->value.'>'.$to->value, $allowed, true)) {
                continue;
            }

            expect($from->canTransitionTo($to))->toBeFalse("{$from->value} → {$to->value} must be invalid");
        }
    }
});

it('knows its final statuses', function () {
    expect(S::Closed->isFinal())->toBeTrue()
        ->and(S::Rejected->isFinal())->toBeTrue()
        ->and(S::Cancelled->isFinal())->toBeTrue()
        ->and(S::Pending->isFinal())->toBeFalse()
        ->and(S::OnHold->isFinal())->toBeFalse();

    expect(S::finalValues())->toBe(['Closed', 'Rejected', 'Cancelled']);
});

it('exposes the database values and Indonesian labels', function () {
    expect(S::InProgress->value)->toBe('In_Progress')
        ->and(S::OnHold->value)->toBe('On_Hold')
        ->and(S::Closed->label())->toBe('Selesai');
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `php artisan test tests/Unit/ReportStatusTest.php`
Expected: FAIL (`Class "App\Enums\ReportStatus" not found`).

- [ ] **Step 3: Create the enums.** `app/Enums/ReportStatus.php`

```php
<?php

namespace App\Enums;

enum ReportStatus: string
{
    case Pending = 'Pending';
    case Assigned = 'Assigned';
    case InProgress = 'In_Progress';
    case OnHold = 'On_Hold';
    case Closed = 'Closed';
    case Rejected = 'Rejected';
    case Cancelled = 'Cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu',
            self::Assigned => 'Ditugaskan',
            self::InProgress => 'Dikerjakan',
            self::OnHold => 'Ditunda',
            self::Closed => 'Selesai',
            self::Rejected => 'Ditolak',
            self::Cancelled => 'Dibatalkan',
        };
    }

    /** @return list<self> */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Pending => [self::Assigned, self::Rejected, self::Cancelled],
            self::Assigned => [self::InProgress],
            self::InProgress => [self::OnHold, self::Closed],
            self::OnHold => [self::InProgress],
            self::Closed, self::Rejected, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedNext(), true);
    }

    public function isFinal(): bool
    {
        return $this->allowedNext() === [];
    }

    /** @return list<string> */
    public static function finalValues(): array
    {
        return array_values(array_map(
            fn (self $status) => $status->value,
            array_filter(self::cases(), fn (self $status) => $status->isFinal()),
        ));
    }
}
```

`app/Enums/Priority.php`

```php
<?php

namespace App\Enums;

enum Priority: string
{
    case Low = 'Low';
    case Medium = 'Medium';
    case High = 'High';
    case Critical = 'Critical';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Rendah',
            self::Medium => 'Sedang',
            self::High => 'Tinggi',
            self::Critical => 'Kritis',
        };
    }

    /** Default SLA in days for this priority (config/siperkasa.php). */
    public function slaDays(): int
    {
        return (int) config('siperkasa.sla_days.'.$this->value);
    }
}
```

`app/Enums/AttachmentType.php`

```php
<?php

namespace App\Enums;

enum AttachmentType: string
{
    case Damage = 'damage';
    case Completion = 'completion';
}
```

`config/siperkasa.php`

```php
<?php

return [
    // Default SLA (days) per official priority.
    'sla_days' => ['Critical' => 1, 'High' => 3, 'Medium' => 7, 'Low' => 14],

    'photos' => ['min' => 1, 'max' => 3, 'max_kb' => 2048],

    'pdf_max_rows' => 1000,
];
```

- [ ] **Step 4: Run the unit test**

Run: `php artisan test tests/Unit/ReportStatusTest.php`
Expected: all pass.

- [ ] **Step 5: Rewrite the reports migration** (`2026_09_28_100002_create_reports_table.php`, whole file)

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number')->unique();               // SPK-YYMM-NNNN

            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('title', 120);
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->foreignId('room_id')->constrained('rooms')->restrictOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained('assets')->nullOnDelete();
            $table->text('description');
            $table->enum('user_priority', ['Low', 'Medium', 'High', 'Critical']);
            $table->enum('status', ['Pending', 'Assigned', 'In_Progress', 'On_Hold', 'Closed', 'Rejected', 'Cancelled'])
                ->default('Pending')->index();

            // Admin validation
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('admin_priority', ['Low', 'Medium', 'High', 'Critical'])->nullable();
            $table->dateTime('sla_date')->nullable()->index();
            $table->foreignId('assigned_pic_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();

            // PIC execution
            $table->text('hold_reason')->nullable();                 // latest reason; history lives in the status log
            $table->text('repair_action')->nullable();
            $table->date('completed_on')->nullable();
            $table->decimal('repair_cost', 15, 2)->nullable();
            $table->string('vendor_name')->nullable();

            $table->timestamp('validated_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['assigned_pic_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
```

- [ ] **Step 6: New migrations.** `2026_09_28_100010_create_report_attachments_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained('reports')->cascadeOnDelete();
            $table->enum('type', ['damage', 'completion']);
            $table->string('path');
            $table->unsignedTinyInteger('position')->default(1);
            $table->timestamps();

            $table->index(['report_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_attachments');
    }
};
```

`2026_09_28_100011_create_report_status_logs_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained('reports')->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['report_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_status_logs');
    }
};
```

Generate the notifications table: `php artisan make:notifications-table`.

- [ ] **Step 7: Models.** Replace `app/Models/Report.php` entirely

```php
<?php

namespace App\Models;

use App\Enums\AttachmentType;
use App\Enums\Priority;
use App\Enums\ReportStatus;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $ticket_number
 * @property int $user_id
 * @property string $title
 * @property int $category_id
 * @property int $room_id
 * @property int|null $asset_id
 * @property string $description
 * @property Priority $user_priority
 * @property ReportStatus $status
 * @property int|null $admin_id
 * @property Priority|null $admin_priority
 * @property Carbon|null $sla_date
 * @property int|null $assigned_pic_id
 * @property string|null $rejection_reason
 * @property string|null $hold_reason
 * @property string|null $repair_action
 * @property Carbon|null $completed_on
 * @property string|null $repair_cost
 * @property string|null $vendor_name
 * @property Carbon|null $validated_at
 * @property Carbon|null $started_at
 * @property Carbon|null $closed_at
 * @property Carbon|null $cancelled_at
 */
class Report extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_number', 'user_id', 'title', 'category_id', 'room_id', 'asset_id', 'description',
        'user_priority', 'status', 'admin_id', 'admin_priority', 'sla_date', 'assigned_pic_id',
        'rejection_reason', 'hold_reason', 'repair_action', 'completed_on', 'repair_cost', 'vendor_name',
        'validated_at', 'started_at', 'closed_at', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ReportStatus::class,
            'user_priority' => Priority::class,
            'admin_priority' => Priority::class,
            'sla_date' => 'datetime',
            'completed_on' => 'date',
            'repair_cost' => 'decimal:2',
            'validated_at' => 'datetime',
            'started_at' => 'datetime',
            'closed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function assignedPic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_pic_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ReportAttachment::class);
    }

    public function damagePhotos(): HasMany
    {
        return $this->attachments()->where('type', AttachmentType::Damage)->orderBy('position');
    }

    public function completionPhotos(): HasMany
    {
        return $this->attachments()->where('type', AttachmentType::Completion)->orderBy('position');
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(ReportStatusLog::class)->orderBy('id');
    }

    /** @param Builder<Report> $query */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return match ($user->role) {
            UserRole::Admin => $query,
            UserRole::Pic => $query->where('assigned_pic_id', $user->id),
            UserRole::User => $query->where('user_id', $user->id),
        };
    }

    /** @param Builder<Report> $query */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->whereNotIn('status', ReportStatus::finalValues())
            ->whereNotNull('sla_date')
            ->where('sla_date', '<', now());
    }

    public function isOverdue(): bool
    {
        return ! $this->status->isFinal() && $this->sla_date !== null && $this->sla_date->isPast();
    }
}
```

`app/Models/ReportAttachment.php`

```php
<?php

namespace App\Models;

use App\Enums\AttachmentType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $report_id
 * @property AttachmentType $type
 * @property string $path
 * @property int $position
 */
class ReportAttachment extends Model
{
    use HasFactory;

    protected $fillable = ['report_id', 'type', 'path', 'position'];

    protected function casts(): array
    {
        return ['type' => AttachmentType::class];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }
}
```

`app/Models/ReportStatusLog.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $report_id
 * @property string|null $from_status
 * @property string $to_status
 * @property int|null $actor_id
 * @property string|null $note
 * @property \Illuminate\Support\Carbon|null $created_at
 */
class ReportStatusLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['report_id', 'from_status', 'to_status', 'actor_id', 'note'];

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
```

In `app/Models/Category.php` add `use Illuminate\Database\Eloquent\Relations\HasMany;` (already imported) and replace `isInUse()` and add `reports()`:

```php
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    public function isInUse(): bool
    {
        return $this->assets()->exists() || $this->reports()->exists();
    }
```

In `app/Models/Room.php` add `use Illuminate\Database\Eloquent\Relations\HasMany;` and replace `isInUse()`:

```php
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    public function isInUse(): bool
    {
        return $this->reports()->exists();
    }
```

In `routes/web.php` change the `/` closure's stats to use the enum (add `use App\Enums\ReportStatus;`):

```php
            'total_completed' => Report::where('status', ReportStatus::Closed)->count(),
            'total_in_progress' => Report::whereIn('status', [ReportStatus::Assigned, ReportStatus::InProgress])->count(),
```

- [ ] **Step 8: Factories.** `database/factories/ReportFactory.php`

```php
<?php

namespace Database\Factories;

use App\Enums\Priority;
use App\Enums\ReportStatus;
use App\Models\Category;
use App\Models\Report;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Report> */
class ReportFactory extends Factory
{
    public function definition(): array
    {
        return [
            // Valid SPK-YYMM-NNNN shape in the unused "99xx" month range, so it never collides
            // with real tickets from TicketNumber::next(), even for ->count(n)->create().
            'ticket_number' => fake()->unique()->numerify('SPK-99##-####'),
            'user_id' => User::factory(),
            'title' => fake()->sentence(4),
            'category_id' => Category::factory(),
            'room_id' => Room::factory(),
            'asset_id' => null,
            'description' => fake()->paragraph(),
            'user_priority' => fake()->randomElement(Priority::cases()),
            'status' => ReportStatus::Pending,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => ReportStatus::Pending]);
    }

    public function assigned(?User $pic = null): static
    {
        return $this->state(fn () => [
            'status' => ReportStatus::Assigned,
            'admin_id' => User::factory()->admin(),
            'admin_priority' => Priority::Medium,
            'sla_date' => now()->addDays(7),
            'assigned_pic_id' => $pic?->id ?? User::factory()->pic(),
            'validated_at' => now(),
        ]);
    }

    public function inProgress(?User $pic = null): static
    {
        return $this->assigned($pic)->state(fn () => [
            'status' => ReportStatus::InProgress,
            'started_at' => now(),
        ]);
    }

    public function onHold(?User $pic = null): static
    {
        return $this->inProgress($pic)->state(fn () => [
            'status' => ReportStatus::OnHold,
            'hold_reason' => 'Menunggu sparepart dari vendor.',
        ]);
    }

    public function closed(?User $pic = null): static
    {
        return $this->inProgress($pic)->state(fn () => [
            'status' => ReportStatus::Closed,
            'repair_action' => 'Ganti lampu proyektor.',
            'completed_on' => now()->toDateString(),
            'repair_cost' => 350000,
            'vendor_name' => 'CV Teknik Jaya',
            'closed_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => ReportStatus::Rejected,
            'admin_id' => User::factory()->admin(),
            'rejection_reason' => 'Laporan duplikat dengan tiket lain.',
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => ReportStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
    }
}
```

`database/factories/ReportAttachmentFactory.php`

```php
<?php

namespace Database\Factories;

use App\Enums\AttachmentType;
use App\Models\Report;
use App\Models\ReportAttachment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ReportAttachment> */
class ReportAttachmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'report_id' => Report::factory(),
            'type' => AttachmentType::Damage,
            'path' => 'reports/test/damage/'.Str::uuid().'.jpg',
            'position' => 1,
        ];
    }

    public function completion(): static
    {
        return $this->state(fn () => ['type' => AttachmentType::Completion]);
    }
}
```

- [ ] **Step 9: Priority and model tests.** `tests/Feature/Reports/PriorityTest.php`

```php
<?php

use App\Enums\Priority;

test('default SLA days per priority come from config', function () {
    expect(Priority::Critical->slaDays())->toBe(1)
        ->and(Priority::High->slaDays())->toBe(3)
        ->and(Priority::Medium->slaDays())->toBe(7)
        ->and(Priority::Low->slaDays())->toBe(14);
});

test('priorities have Indonesian labels', function () {
    expect(Priority::Critical->label())->toBe('Kritis');
});
```

`tests/Feature/Reports/ReportModelTest.php`

```php
<?php

use App\Enums\ReportStatus;
use App\Models\Category;
use App\Models\Report;
use App\Models\Room;
use App\Models\User;

test('visibleTo scopes reports per role', function () {
    $reporter = User::factory()->create();
    $pic = User::factory()->pic()->create();
    $admin = User::factory()->admin()->create();

    $mine = Report::factory()->for($reporter)->create();
    $assigned = Report::factory()->assigned($pic)->create();
    Report::factory()->create();

    expect(Report::visibleTo($reporter)->pluck('id')->all())->toBe([$mine->id]);
    expect(Report::visibleTo($pic)->pluck('id')->all())->toBe([$assigned->id]);
    expect(Report::visibleTo($admin)->count())->toBe(3);
});

test('overdue only counts non-final reports past their SLA', function () {
    $late = Report::factory()->assigned()->create(['sla_date' => now()->subDay()]);
    Report::factory()->assigned()->create(['sla_date' => now()->addDay()]);
    Report::factory()->closed()->create(['sla_date' => now()->subDay()]);
    Report::factory()->create(['sla_date' => null]);

    expect(Report::overdue()->pluck('id')->all())->toBe([$late->id]);
    expect($late->isOverdue())->toBeTrue();
});

test('categories and rooms used by a report are protected from deletion', function () {
    $report = Report::factory()->create();

    expect($report->category->isInUse())->toBeTrue()
        ->and($report->room->isInUse())->toBeTrue()
        ->and(Category::factory()->create()->isInUse())->toBeFalse()
        ->and(Room::factory()->create()->isInUse())->toBeFalse();
});

test('factory states produce the matching status', function (string $state, ReportStatus $status) {
    expect(Report::factory()->{$state}()->create()->status)->toBe($status);
})->with([
    ['pending', ReportStatus::Pending],
    ['assigned', ReportStatus::Assigned],
    ['inProgress', ReportStatus::InProgress],
    ['onHold', ReportStatus::OnHold],
    ['closed', ReportStatus::Closed],
    ['rejected', ReportStatus::Rejected],
    ['cancelled', ReportStatus::Cancelled],
]);
```

- [ ] **Step 10: Refresh the dev DB and run the tests**

Run: `php artisan migrate:fresh --seed && php artisan test tests/Feature/Reports tests/Unit`
Expected: all pass.

- [ ] **Step 11: Commit**

```bash
git add -A
git commit -m "feat: report domain (status machine, schema, models, factories)"
```

---

### Task 2: Ticket numbers and the status transitioner

**Files:**
- Create: `app/Support/TicketNumber.php`, `app/Actions/Reports/{ReportTransitioner,InvalidTransition}.php`, `tests/Feature/Reports/{TicketNumberTest,ReportTransitionerTest}.php`

**Interfaces:**
- Produces:
  - `TicketNumber::next(?CarbonInterface $now = null): string` → `SPK-YYMM-NNNN`.
  - `ReportTransitioner::transition(Report $report, ReportStatus $to, ?User $actor, ?string $note = null, array $attributes = []): Report` — validates, saves `status` + extra `$attributes`, writes the log; throws `InvalidTransition`.
  - `ReportTransitioner::note(Report $report, ?User $actor, string $note): void` — same-status log row.
  - `ReportTransitioner::logCreated(Report $report, ?User $actor): void` — log with `from_status = null`.
  - `InvalidTransition extends DomainException`.

- [ ] **Step 1: Write the failing tests.** `tests/Feature/Reports/TicketNumberTest.php`

```php
<?php

use App\Models\Report;
use App\Support\TicketNumber;
use Illuminate\Support\Carbon;

test('the first ticket of a month ends in 0001 and the next increments', function () {
    $this->travelTo(Carbon::parse('2026-10-15 09:00', 'Asia/Jakarta'));

    expect(TicketNumber::next())->toBe('SPK-2610-0001');

    Report::factory()->create(['ticket_number' => 'SPK-2610-0001']);

    expect(TicketNumber::next())->toBe('SPK-2610-0002');
});

test('numbering restarts every month', function () {
    $this->travelTo(Carbon::parse('2026-10-31 23:00', 'Asia/Jakarta'));
    Report::factory()->create(['ticket_number' => 'SPK-2610-0007']);

    $this->travelTo(Carbon::parse('2026-11-01 08:00', 'Asia/Jakarta'));

    expect(TicketNumber::next())->toBe('SPK-2611-0001');
});

test('five reports in a row get unique increasing tickets', function () {
    $this->travelTo(Carbon::parse('2026-10-15 09:00', 'Asia/Jakarta'));

    $tickets = [];

    foreach (range(1, 5) as $ignored) {
        $tickets[] = $ticket = TicketNumber::next();
        Report::factory()->create(['ticket_number' => $ticket]);
    }

    expect($tickets)->toBe([
        'SPK-2610-0001', 'SPK-2610-0002', 'SPK-2610-0003', 'SPK-2610-0004', 'SPK-2610-0005',
    ]);
});

test('an explicit date can be passed', function () {
    expect(TicketNumber::next(Carbon::parse('2027-01-05')))->toBe('SPK-2701-0001');
});
```

`tests/Feature/Reports/ReportTransitionerTest.php`

```php
<?php

use App\Actions\Reports\InvalidTransition;
use App\Actions\Reports\ReportTransitioner;
use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\User;

test('a valid transition updates the report and writes one log row', function () {
    $actor = User::factory()->admin()->create();
    $report = Report::factory()->create();

    app(ReportTransitioner::class)->transition($report, ReportStatus::Rejected, $actor, 'Duplikat', [
        'rejection_reason' => 'Duplikat',
        'admin_id' => $actor->id,
    ]);

    $report->refresh();
    expect($report->status)->toBe(ReportStatus::Rejected)
        ->and($report->rejection_reason)->toBe('Duplikat');

    $log = $report->statusLogs()->sole();
    expect($log->from_status)->toBe('Pending')
        ->and($log->to_status)->toBe('Rejected')
        ->and($log->actor_id)->toBe($actor->id)
        ->and($log->note)->toBe('Duplikat');
});

test('an invalid transition throws and changes nothing', function () {
    $report = Report::factory()->closed()->create();

    expect(fn () => app(ReportTransitioner::class)->transition($report, ReportStatus::InProgress, null))
        ->toThrow(InvalidTransition::class);

    expect($report->fresh()->status)->toBe(ReportStatus::Closed)
        ->and($report->statusLogs()->count())->toBe(0);
});

test('staying in the same status is not a transition', function () {
    $report = Report::factory()->create();

    expect(fn () => app(ReportTransitioner::class)->transition($report, ReportStatus::Pending, null))
        ->toThrow(InvalidTransition::class);
});

test('note() writes a same-status log row', function () {
    $user = User::factory()->create();
    $report = Report::factory()->for($user)->create();

    app(ReportTransitioner::class)->note($report, $user, 'Laporan diubah');

    $log = $report->statusLogs()->sole();
    expect($log->from_status)->toBe('Pending')->and($log->to_status)->toBe('Pending')->and($log->note)->toBe('Laporan diubah');
});

test('logCreated() records the creation with no previous status', function () {
    $user = User::factory()->create();
    $report = Report::factory()->for($user)->create();

    app(ReportTransitioner::class)->logCreated($report, $user);

    $log = $report->statusLogs()->sole();
    expect($log->from_status)->toBeNull()->and($log->to_status)->toBe('Pending');
});
```

- [ ] **Step 2: Run to verify they fail**

Run: `php artisan test tests/Feature/Reports/TicketNumberTest.php tests/Feature/Reports/ReportTransitionerTest.php`
Expected: FAIL (`Class "App\Support\TicketNumber" not found`).

- [ ] **Step 3: Create `app/Support/TicketNumber.php`**

```php
<?php

namespace App\Support;

use App\Models\Report;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

final class TicketNumber
{
    /** Next ticket for the month of $now, e.g. SPK-2610-0001 (restarts monthly). */
    public static function next(?CarbonInterface $now = null): string
    {
        $prefix = 'SPK-'.($now ?? now())->format('ym').'-';

        return DB::transaction(function () use ($prefix) {
            $last = Report::query()
                ->where('ticket_number', 'like', $prefix.'%')
                ->orderByDesc('ticket_number')
                ->lockForUpdate()
                ->value('ticket_number');

            $sequence = $last === null ? 1 : ((int) substr($last, -4)) + 1;

            return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
        });
    }
}
```

- [ ] **Step 4: Create the transitioner.** `app/Actions/Reports/InvalidTransition.php`

```php
<?php

namespace App\Actions\Reports;

use App\Enums\ReportStatus;
use DomainException;

class InvalidTransition extends DomainException
{
    public static function between(ReportStatus $from, ReportStatus $to): self
    {
        return new self("Status laporan tidak bisa berubah dari {$from->value} ke {$to->value}.");
    }
}
```

`app/Actions/Reports/ReportTransitioner.php`

```php
<?php

namespace App\Actions\Reports;

use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\ReportStatusLog;
use App\Models\User;

/** The only place that changes a report's status. Call it inside a DB transaction. */
final class ReportTransitioner
{
    /**
     * @param  array<string, mixed>  $attributes  extra columns saved together with the new status
     *
     * @throws InvalidTransition
     */
    public function transition(Report $report, ReportStatus $to, ?User $actor, ?string $note = null, array $attributes = []): Report
    {
        $from = $report->status;

        if (! $from->canTransitionTo($to)) {
            throw InvalidTransition::between($from, $to);
        }

        $report->forceFill([...$attributes, 'status' => $to])->save();

        $this->log($report, $from->value, $to->value, $actor, $note);

        return $report;
    }

    /** Records an event that does not change the status (edit, reassign). */
    public function note(Report $report, ?User $actor, string $note): void
    {
        $this->log($report, $report->status->value, $report->status->value, $actor, $note);
    }

    public function logCreated(Report $report, ?User $actor): void
    {
        $this->log($report, null, $report->status->value, $actor, null);
    }

    private function log(Report $report, ?string $from, string $to, ?User $actor, ?string $note): void
    {
        ReportStatusLog::create([
            'report_id' => $report->id,
            'from_status' => $from,
            'to_status' => $to,
            'actor_id' => $actor?->id,
            'note' => $note,
        ]);
    }
}
```

- [ ] **Step 5: Run the tests**

Run: `php artisan test tests/Feature/Reports tests/Unit`
Expected: all pass (this also completes Task 1's tests).

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat: monthly ticket numbers and the report status transitioner"
```

---

### Task 3: Notification classes

**Files:**
- Create: `app/Support/ReportUrl.php`, `app/Notifications/{ReportSubmitted,ReportAssigned,ReportStatusChanged}.php`, `tests/Feature/Notifications/ReportNotificationPayloadTest.php`

**Interfaces:**
- Produces: `ReportSubmitted(Report $report)` (to admins), `ReportAssigned(Report $report)` (to the PIC), `ReportStatusChanged(Report $report, ?string $message = null)` (to the reporter). All use channel `database` and `toArray()` returns `['report_id','ticket_number','status','message','url']`. `ReportUrl::for(Report, User $recipient): string`.
- Note: `admin.reports.show` and `pic.tasks.show` are created in Plan 4. Until then `ReportUrl` falls back to `route('dashboard')`.

- [ ] **Step 1: Write the failing test** `tests/Feature/Notifications/ReportNotificationPayloadTest.php`

```php
<?php

use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\User;
use App\Notifications\ReportAssigned;
use App\Notifications\ReportStatusChanged;
use App\Notifications\ReportSubmitted;

test('all notifications use only the database channel', function () {
    $report = Report::factory()->create();
    $user = User::factory()->create();

    foreach ([new ReportSubmitted($report), new ReportAssigned($report), new ReportStatusChanged($report)] as $notification) {
        expect($notification->via($user))->toBe(['database']);
    }
});

test('submitted notification carries the standard payload', function () {
    $report = Report::factory()->create(['title' => 'Proyektor mati']);
    $admin = User::factory()->admin()->create();

    $payload = (new ReportSubmitted($report))->toArray($admin);

    expect($payload)->toHaveKeys(['report_id', 'ticket_number', 'status', 'message', 'url'])
        ->and($payload['report_id'])->toBe($report->id)
        ->and($payload['ticket_number'])->toBe($report->ticket_number)
        ->and($payload['status'])->toBe('Pending')
        ->and($payload['message'])->toBe("Laporan baru {$report->ticket_number}: Proyektor mati.");
});

test('assigned notification tells the pic about the task', function () {
    $report = Report::factory()->assigned()->create(['title' => 'AC bocor']);

    $payload = (new ReportAssigned($report))->toArray($report->assignedPic);

    expect($payload['message'])->toBe("Anda ditugaskan menangani laporan {$report->ticket_number}: AC bocor.")
        ->and($payload['status'])->toBe('Assigned');
});

test('status changed notification has a message per status', function (ReportStatus $status, string $expected) {
    $report = Report::factory()->create();
    $report->status = $status;

    $payload = (new ReportStatusChanged($report))->toArray($report->user);

    expect($payload['message'])->toBe(str_replace('{t}', $report->ticket_number, $expected));
})->with([
    [ReportStatus::Assigned, 'Laporan {t} sudah divalidasi dan ditugaskan ke PIC.'],
    [ReportStatus::InProgress, 'Laporan {t} sedang dikerjakan.'],
    [ReportStatus::OnHold, 'Laporan {t} ditunda sementara.'],
    [ReportStatus::Closed, 'Laporan {t} sudah selesai.'],
    [ReportStatus::Rejected, 'Laporan {t} ditolak.'],
]);

test('a custom message overrides the default', function () {
    $report = Report::factory()->create();

    $payload = (new ReportStatusChanged($report, 'Pesan khusus'))->toArray($report->user);

    expect($payload['message'])->toBe('Pesan khusus');
});

test('the url is a safe fallback when role routes do not exist yet', function () {
    $report = Report::factory()->create();
    $admin = User::factory()->admin()->create();

    expect((new ReportSubmitted($report))->toArray($admin)['url'])->toBeString()->not->toBeEmpty();
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `php artisan test tests/Feature/Notifications/ReportNotificationPayloadTest.php`
Expected: FAIL (`Class "App\Notifications\ReportSubmitted" not found`).

- [ ] **Step 3: Create `app/Support/ReportUrl.php`**

```php
<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Facades\Route;

final class ReportUrl
{
    /** The detail page of the report as seen by the recipient's role. */
    public static function for(Report $report, User $recipient): string
    {
        $name = match ($recipient->role) {
            UserRole::Admin => 'admin.reports.show',
            UserRole::Pic => 'pic.tasks.show',
            UserRole::User => 'reports.show',
        };

        return Route::has($name) ? route($name, $report) : route('dashboard');
    }
}
```

- [ ] **Step 4: Create the three notifications.** `app/Notifications/ReportSubmitted.php`

```php
<?php

namespace App\Notifications;

use App\Models\Report;
use App\Support\ReportUrl;
use Illuminate\Notifications\Notification;

class ReportSubmitted extends Notification
{
    public function __construct(public Report $report) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'report_id' => $this->report->id,
            'ticket_number' => $this->report->ticket_number,
            'status' => $this->report->status->value,
            'message' => "Laporan baru {$this->report->ticket_number}: {$this->report->title}.",
            'url' => ReportUrl::for($this->report, $notifiable),
        ];
    }
}
```

`app/Notifications/ReportAssigned.php`

```php
<?php

namespace App\Notifications;

use App\Models\Report;
use App\Support\ReportUrl;
use Illuminate\Notifications\Notification;

class ReportAssigned extends Notification
{
    public function __construct(public Report $report) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'report_id' => $this->report->id,
            'ticket_number' => $this->report->ticket_number,
            'status' => $this->report->status->value,
            'message' => "Anda ditugaskan menangani laporan {$this->report->ticket_number}: {$this->report->title}.",
            'url' => ReportUrl::for($this->report, $notifiable),
        ];
    }
}
```

`app/Notifications/ReportStatusChanged.php`

```php
<?php

namespace App\Notifications;

use App\Enums\ReportStatus;
use App\Models\Report;
use App\Support\ReportUrl;
use Illuminate\Notifications\Notification;

class ReportStatusChanged extends Notification
{
    public function __construct(public Report $report, public ?string $message = null) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $ticket = $this->report->ticket_number;

        return [
            'report_id' => $this->report->id,
            'ticket_number' => $ticket,
            'status' => $this->report->status->value,
            'message' => $this->message ?? match ($this->report->status) {
                ReportStatus::Assigned => "Laporan {$ticket} sudah divalidasi dan ditugaskan ke PIC.",
                ReportStatus::InProgress => "Laporan {$ticket} sedang dikerjakan.",
                ReportStatus::OnHold => "Laporan {$ticket} ditunda sementara.",
                ReportStatus::Closed => "Laporan {$ticket} sudah selesai.",
                ReportStatus::Rejected => "Laporan {$ticket} ditolak.",
                default => "Status laporan {$ticket}: {$this->report->status->label()}.",
            },
            'url' => ReportUrl::for($this->report, $notifiable),
        ];
    }
}
```

- [ ] **Step 5: Run the test**

Run: `php artisan test tests/Feature/Notifications/ReportNotificationPayloadTest.php`
Expected: all pass.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat: in-app notification classes for report events"
```

---

### Task 4: `ReportPolicy`

**Files:**
- Create: `app/Policies/ReportPolicy.php`, `tests/Feature/Reports/ReportPolicyTest.php`

**Interfaces:**
- Produces abilities on a `Report` (used with `Gate::authorize` / `$user->can`): `view`, `update`, `cancel`, `validate`, `reject`, `reassign`, `start`, `hold`, `resume`, `complete`; and class-level `viewAny`, `create`.

- [ ] **Step 1: Write the exhaustive failing test** `tests/Feature/Reports/ReportPolicyTest.php`

```php
<?php

use App\Enums\ReportStatus as S;
use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

const ABILITIES = ['view', 'update', 'cancel', 'validate', 'reject', 'reassign', 'start', 'hold', 'resume', 'complete'];

/** @return list<string> */
function expectedAbilities(string $actor, S $status, bool $assigned): array
{
    $pending = $status === S::Pending;

    return array_values(array_filter(match ($actor) {
        'owner' => ['view', $pending ? 'update' : null, $pending ? 'cancel' : null],
        'admin' => [
            'view',
            $pending ? 'validate' : null,
            $pending ? 'reject' : null,
            in_array($status, [S::Assigned, S::InProgress, S::OnHold], true) ? 'reassign' : null,
        ],
        'assigned_pic' => $assigned ? [
            'view',
            $status === S::Assigned ? 'start' : null,
            $status === S::InProgress ? 'hold' : null,
            $status === S::OnHold ? 'resume' : null,
            $status === S::InProgress ? 'complete' : null,
        ] : [],
        default => [], // other_user, other_pic
    }));
}

$matrix = [];
foreach (['owner', 'other_user', 'admin', 'assigned_pic', 'other_pic'] as $actor) {
    foreach (S::cases() as $status) {
        $matrix["{$actor} / {$status->value}"] = [$actor, $status];
    }
}

it('grants exactly the right abilities', function (string $actor, S $status) {
    $owner = User::factory()->create();
    $pic = User::factory()->pic()->create();
    $assigned = in_array($status, [S::Assigned, S::InProgress, S::OnHold, S::Closed], true);

    $report = Report::factory()->for($owner)->create([
        'status' => $status,
        'assigned_pic_id' => $assigned ? $pic->id : null,
    ]);

    $user = match ($actor) {
        'owner' => $owner,
        'other_user' => User::factory()->create(),
        'admin' => User::factory()->admin()->create(),
        'assigned_pic' => $pic,
        'other_pic' => User::factory()->pic()->create(),
    };

    $expected = expectedAbilities($actor, $status, $assigned);

    foreach (ABILITIES as $ability) {
        expect(Gate::forUser($user)->allows($ability, $report))
            ->toBe(in_array($ability, $expected, true), "{$actor} / {$status->value} / {$ability}");
    }
})->with($matrix);

it('only reporters can create reports', function () {
    expect(Gate::forUser(User::factory()->create())->allows('create', Report::class))->toBeTrue()
        ->and(Gate::forUser(User::factory()->admin()->create())->allows('create', Report::class))->toBeFalse()
        ->and(Gate::forUser(User::factory()->pic()->create())->allows('create', Report::class))->toBeFalse();
});

it('lets every role open the report lists', function () {
    foreach (['reporter', 'admin', 'pic'] as $state) {
        expect(Gate::forUser(User::factory()->{$state}()->create())->allows('viewAny', Report::class))->toBeTrue();
    }
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `php artisan test tests/Feature/Reports/ReportPolicyTest.php`
Expected: FAIL (no policy registered: all abilities denied, so the owner/admin cases fail).

- [ ] **Step 3: Create `app/Policies/ReportPolicy.php`**

```php
<?php

namespace App\Policies;

use App\Enums\ReportStatus;
use App\Enums\UserRole;
use App\Models\Report;
use App\Models\User;

class ReportPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // lists are narrowed with Report::visibleTo()
    }

    public function view(User $user, Report $report): bool
    {
        return match ($user->role) {
            UserRole::Admin => true,
            UserRole::User => $report->user_id === $user->id,
            UserRole::Pic => $report->assigned_pic_id === $user->id,
        };
    }

    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::User);
    }

    public function update(User $user, Report $report): bool
    {
        return $this->ownsWhilePending($user, $report);
    }

    public function cancel(User $user, Report $report): bool
    {
        return $this->ownsWhilePending($user, $report);
    }

    public function validate(User $user, Report $report): bool
    {
        return $user->hasRole(UserRole::Admin) && $report->status === ReportStatus::Pending;
    }

    public function reject(User $user, Report $report): bool
    {
        return $this->validate($user, $report);
    }

    public function reassign(User $user, Report $report): bool
    {
        return $user->hasRole(UserRole::Admin)
            && in_array($report->status, [ReportStatus::Assigned, ReportStatus::InProgress, ReportStatus::OnHold], true);
    }

    public function start(User $user, Report $report): bool
    {
        return $this->isAssignedPic($user, $report) && $report->status === ReportStatus::Assigned;
    }

    public function hold(User $user, Report $report): bool
    {
        return $this->isAssignedPic($user, $report) && $report->status === ReportStatus::InProgress;
    }

    public function resume(User $user, Report $report): bool
    {
        return $this->isAssignedPic($user, $report) && $report->status === ReportStatus::OnHold;
    }

    public function complete(User $user, Report $report): bool
    {
        return $this->isAssignedPic($user, $report) && $report->status === ReportStatus::InProgress;
    }

    private function ownsWhilePending(User $user, Report $report): bool
    {
        return $user->hasRole(UserRole::User)
            && $report->user_id === $user->id
            && $report->status === ReportStatus::Pending;
    }

    private function isAssignedPic(User $user, Report $report): bool
    {
        return $user->hasRole(UserRole::Pic) && $report->assigned_pic_id === $user->id;
    }
}
```

Laravel auto-discovers `App\Policies\ReportPolicy` for `App\Models\Report`.

- [ ] **Step 4: Run the test**

Run: `php artisan test tests/Feature/Reports/ReportPolicyTest.php`
Expected: 37 passed (35 matrix cases + 2).

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat: report policy covering every actor and status"
```

---

### Task 5: Submit a report (backend) and photo serving

**Files:**
- Create: `app/Actions/Reports/{AttachmentStore,SubmitReport}.php`, `app/Http/Requests/StoreReportRequest.php`, `app/Support/ReportPresenter.php`, `app/Http/Controllers/User/ReportController.php`, `app/Http/Controllers/AttachmentController.php`, `tests/Support/ReportInput.php`, `tests/Feature/Reports/{SubmitReportTest,AttachmentAccessTest}.php`
- Modify: `routes/web.php`

**Interfaces:**
- Produces:
  - `AttachmentStore::add(Report, AttachmentType, array $files): void` and `::remove(Report, AttachmentType, array $ids): void`.
  - `SubmitReport::handle(User $user, array $data, array $photos): Report`.
  - `ReportPresenter::summary(Report): array` and `::detail(Report): array` (shape in Step 5).
  - Routes `reports.create`, `reports.store`, `reports.show`, `attachments.show`.
  - `Tests\Support\ReportInput::valid(array $overrides = []): array` — a valid POST payload with one fake photo.

- [ ] **Step 1: Test helper** `tests/Support/ReportInput.php`

```php
<?php

namespace Tests\Support;

use App\Models\Category;
use App\Models\Room;
use Illuminate\Http\UploadedFile;

final class ReportInput
{
    /** A valid report form payload (with one fake damage photo). */
    public static function valid(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Proyektor tidak menyala',
            'category_id' => Category::factory()->create()->id,
            'room_id' => Room::factory()->create()->id,
            'asset_id' => null,
            'description' => 'Proyektor di ruang 101 tidak menyala sejak pagi.',
            'user_priority' => 'High',
            'photos' => [UploadedFile::fake()->image('rusak.jpg')],
        ], $overrides);
    }
}
```

- [ ] **Step 2: Write the failing tests.** `tests/Feature/Reports/SubmitReportTest.php`

```php
<?php

use App\Enums\AttachmentType;
use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\User;
use App\Notifications\ReportSubmitted;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ReportInput;

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
    $this->reporter = User::factory()->create();
});

test('the create form lists categories, buildings with rooms and priorities', function () {
    $this->actingAs($this->reporter)->get(route('reports.create'))
        ->assertInertia(fn ($page) => $page
            ->component('reports/Create')
            ->has('categories')
            ->has('buildings')
            ->has('priorities', 4));
});

test('submitting stores a pending report with ticket, photo and log', function () {
    $response = $this->actingAs($this->reporter)->post(route('reports.store'), ReportInput::valid());

    $report = Report::sole();
    $response->assertRedirect(route('reports.show', $report));

    expect($report->status)->toBe(ReportStatus::Pending)
        ->and($report->user_id)->toBe($this->reporter->id)
        ->and($report->ticket_number)->toMatch('/^SPK-\d{4}-0001$/)
        ->and($report->asset_id)->toBeNull();

    $photo = $report->attachments()->sole();
    expect($photo->type)->toBe(AttachmentType::Damage)->and($photo->position)->toBe(1);
    Storage::disk('local')->assertExists($photo->path);

    $log = $report->statusLogs()->sole();
    expect($log->from_status)->toBeNull()->and($log->to_status)->toBe('Pending')->and($log->actor_id)->toBe($this->reporter->id);
});

test('a second report gets the next ticket', function () {
    $this->actingAs($this->reporter)->post(route('reports.store'), ReportInput::valid());
    $this->actingAs($this->reporter)->post(route('reports.store'), ReportInput::valid());

    expect(Report::orderBy('id')->pluck('ticket_number')->map(fn ($t) => substr($t, -4))->all())->toBe(['0001', '0002']);
});

test('every active admin is notified, inactive admins and others are not', function () {
    $admins = User::factory()->admin()->count(2)->create();
    $inactive = User::factory()->admin()->inactive()->create();
    $pic = User::factory()->pic()->create();

    $this->actingAs($this->reporter)->post(route('reports.store'), ReportInput::valid());

    Notification::assertSentTo($admins, ReportSubmitted::class);
    Notification::assertNotSentTo([$inactive, $pic, $this->reporter], ReportSubmitted::class);
});

test('an asset is optional and can be attached', function () {
    $asset = \App\Models\Asset::factory()->create();

    $this->actingAs($this->reporter)->post(route('reports.store'), ReportInput::valid(['asset_id' => $asset->id]));

    expect(Report::sole()->asset_id)->toBe($asset->id);
});

test('required fields are validated with Indonesian messages', function () {
    $this->actingAs($this->reporter)->post(route('reports.store'), [])
        ->assertSessionHasErrors([
            'title' => 'Judul wajib diisi.',
            'category_id' => 'Kategori wajib diisi.',
            'room_id' => 'Ruang wajib diisi.',
            'description' => 'Deskripsi wajib diisi.',
            'user_priority' => 'Urgensi wajib diisi.',
            'photos' => 'Minimal 1 foto kerusakan.',
        ]);

    expect(Report::count())->toBe(0);
});

test('the title may be exactly 120 characters, counting multibyte characters once', function () {
    $this->actingAs($this->reporter)->post(route('reports.store'), ReportInput::valid(['title' => str_repeat('é', 120)]))
        ->assertSessionHasNoErrors();
    expect(Report::count())->toBe(1);

    $this->actingAs($this->reporter)->post(route('reports.store'), ReportInput::valid(['title' => str_repeat('é', 121)]))
        ->assertSessionHasErrors('title');
    expect(Report::count())->toBe(1);
});

test('the description needs 10 to 2000 characters', function () {
    $this->actingAs($this->reporter)->post(route('reports.store'), ReportInput::valid(['description' => 'pendek']))->assertSessionHasErrors('description');
    $this->actingAs($this->reporter)->post(route('reports.store'), ReportInput::valid(['description' => str_repeat('a', 2001)]))->assertSessionHasErrors('description');
    $this->actingAs($this->reporter)->post(route('reports.store'), ReportInput::valid(['description' => str_repeat('a', 2000)]))->assertSessionHasNoErrors();
});

test('photos: one to three images up to 2048 KB', function () {
    $post = fn (array $photos) => $this->actingAs($this->reporter)->post(route('reports.store'), ReportInput::valid(['photos' => $photos]));
    $image = fn (string $name = 'a.jpg', int $kb = 100) => UploadedFile::fake()->image($name)->size($kb);

    $post([])->assertSessionHasErrors(['photos' => 'Minimal 1 foto kerusakan.']);
    $post([$image(), $image('b.jpg'), $image('c.png'), $image('d.png')])->assertSessionHasErrors(['photos' => 'Maksimal 3 foto.']);
    $post([$image(kb: 2049)])->assertSessionHasErrors('photos.0');
    $post([UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf')])->assertSessionHasErrors('photos.0');

    expect(Report::count())->toBe(0);

    $post([$image(kb: 2048), $image('b.png'), $image('c.jpg')])->assertSessionHasNoErrors();
    expect(Report::sole()->attachments()->count())->toBe(3);
});

test('the room, category and asset must exist', function () {
    $this->actingAs($this->reporter)->post(route('reports.store'), ReportInput::valid(['room_id' => 9999, 'category_id' => 9999, 'asset_id' => 9999]))
        ->assertSessionHasErrors(['room_id', 'category_id', 'asset_id']);
});

test('admins and pics can not create reports', function (string $state) {
    $this->actingAs(User::factory()->{$state}()->create())
        ->post(route('reports.store'), ReportInput::valid())->assertForbidden();

    expect(Report::count())->toBe(0);
})->with(['admin', 'pic']);

test('guests are sent to the login page', function () {
    $this->get(route('reports.create'))->assertRedirect(route('login'));
});
```

`tests/Feature/Reports/AttachmentAccessTest.php`

```php
<?php

use App\Models\Report;
use App\Models\ReportAttachment;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->owner = User::factory()->create();
    $this->pic = User::factory()->pic()->create();
    $this->report = Report::factory()->for($this->owner)->assigned($this->pic)->create();
    $this->attachment = ReportAttachment::factory()->for($this->report)->create();
    Storage::disk('local')->put($this->attachment->path, 'fake-image-bytes');
});

test('people who can see the report can open its photos', function (string $who) {
    $user = match ($who) {
        'owner' => $this->owner,
        'pic' => $this->pic,
        'admin' => User::factory()->admin()->create(),
    };

    $this->actingAs($user)->get(route('attachments.show', $this->attachment))->assertOk();
})->with(['owner', 'pic', 'admin']);

test('everyone else gets 403', function (string $who) {
    $user = match ($who) {
        'other reporter' => User::factory()->create(),
        'other pic' => User::factory()->pic()->create(),
    };

    $this->actingAs($user)->get(route('attachments.show', $this->attachment))->assertForbidden();
})->with(['other reporter', 'other pic']);

test('guests can not open photos', function () {
    $this->get(route('attachments.show', $this->attachment))->assertRedirect(route('login'));
});

test('a missing file is a 404', function () {
    Storage::disk('local')->delete($this->attachment->path);

    $this->actingAs($this->owner)->get(route('attachments.show', $this->attachment))->assertNotFound();
});

test('photos are not reachable from a public URL', function () {
    expect(Storage::disk('public')->exists($this->attachment->path))->toBeFalse();
});
```

- [ ] **Step 3: Run to verify they fail**

Run: `php artisan test tests/Feature/Reports/SubmitReportTest.php tests/Feature/Reports/AttachmentAccessTest.php`
Expected: FAIL (`Route [reports.create] not defined`).

- [ ] **Step 4: Create `AttachmentStore` and `SubmitReport`.** `app/Actions/Reports/AttachmentStore.php`

```php
<?php

namespace App\Actions\Reports;

use App\Enums\AttachmentType;
use App\Models\Report;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

final class AttachmentStore
{
    /** @param  array<int, UploadedFile>  $files */
    public function add(Report $report, AttachmentType $type, array $files): void
    {
        $position = (int) $report->attachments()->where('type', $type)->max('position');

        foreach ($files as $file) {
            $path = $file->store("reports/{$report->id}/{$type->value}", 'local');

            $report->attachments()->create([
                'type' => $type,
                'path' => $path,
                'position' => ++$position,
            ]);
        }
    }

    /**
     * Deletes the given attachments, but only if they belong to this report and type.
     *
     * @param  array<int, int|string>  $ids
     */
    public function remove(Report $report, AttachmentType $type, array $ids): void
    {
        $report->attachments()->where('type', $type)->whereIn('id', $ids)->get()
            ->each(function ($attachment) {
                Storage::disk('local')->delete($attachment->path);
                $attachment->delete();
            });
    }
}
```

`app/Actions/Reports/SubmitReport.php`

```php
<?php

namespace App\Actions\Reports;

use App\Enums\AttachmentType;
use App\Enums\ReportStatus;
use App\Enums\UserRole;
use App\Models\Report;
use App\Models\User;
use App\Notifications\ReportSubmitted;
use App\Support\TicketNumber;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

final class SubmitReport
{
    public function __construct(
        private readonly ReportTransitioner $transitioner,
        private readonly AttachmentStore $attachments,
    ) {}

    /**
     * @param  array{title: string, category_id: int, room_id: int, asset_id?: int|null, description: string, user_priority: string}  $data
     * @param  array<int, UploadedFile>  $photos
     */
    public function handle(User $user, array $data, array $photos): Report
    {
        $report = DB::transaction(function () use ($user, $data, $photos) {
            $report = Report::create([
                ...$data,
                'user_id' => $user->id,
                'ticket_number' => TicketNumber::next(),
                'status' => ReportStatus::Pending,
            ]);

            $this->attachments->add($report, AttachmentType::Damage, $photos);
            $this->transitioner->logCreated($report, $user);

            return $report;
        });

        Notification::send(
            User::query()->where('role', UserRole::Admin)->where('is_active', true)->get(),
            new ReportSubmitted($report),
        );

        return $report;
    }
}
```

- [ ] **Step 5: `StoreReportRequest`** `app/Http/Requests/StoreReportRequest.php`

```php
<?php

namespace App\Http\Requests;

use App\Enums\Priority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReportRequest extends FormRequest
{
    public function rules(): array
    {
        $photos = config('siperkasa.photos');

        return [
            'title' => ['required', 'string', 'max:120'],
            'category_id' => ['required', Rule::exists('categories', 'id')],
            'room_id' => ['required', Rule::exists('rooms', 'id')],
            'asset_id' => ['nullable', Rule::exists('assets', 'id')],
            'description' => ['required', 'string', 'min:10', 'max:2000'],
            'user_priority' => ['required', Rule::enum(Priority::class)],
            'photos' => ['required', 'array', "min:{$photos['min']}", "max:{$photos['max']}"],
            'photos.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', "max:{$photos['max_kb']}"],
        ];
    }

    public function messages(): array
    {
        return [
            'photos.required' => 'Minimal 1 foto kerusakan.',
            'photos.min' => 'Minimal 1 foto kerusakan.',
            'photos.max' => 'Maksimal 3 foto.',
            'photos.*.image' => 'Berkas harus berupa gambar.',
            'photos.*.mimes' => 'Foto harus berformat jpg, png, atau webp.',
            'photos.*.max' => 'Ukuran foto maksimal 2 MB.',
            'photos.*.uploaded' => 'Foto gagal diunggah. Coba lagi.',
        ];
    }
}
```

The `title` rule `max:120` counts characters (`mb_strlen`), so 120 `é` pass and 121 fail.

- [ ] **Step 6: `ReportPresenter`** `app/Support/ReportPresenter.php`

```php
<?php

namespace App\Support;

use App\Enums\AttachmentType;
use App\Models\Report;

/** Turns a Report into the arrays the Inertia pages consume. */
final class ReportPresenter
{
    /** @return array<string, mixed> */
    public static function summary(Report $report): array
    {
        return [
            'id' => $report->id,
            'ticket_number' => $report->ticket_number,
            'title' => $report->title,
            'status' => $report->status->value,
            'status_label' => $report->status->label(),
            'user_priority' => $report->user_priority->value,
            'admin_priority' => $report->admin_priority?->value,
            'is_overdue' => $report->isOverdue(),
            'sla_date' => $report->sla_date?->toIso8601String(),
            'created_at' => $report->created_at?->toIso8601String(),
            'category' => $report->category?->name,
            'room' => $report->room?->name,
            'building' => $report->room?->building?->name,
            'reporter' => $report->user?->name,
            'pic' => $report->assignedPic?->name,
        ];
    }

    /** @return array<string, mixed> */
    public static function detail(Report $report): array
    {
        $report->loadMissing(['category', 'room.building', 'asset', 'user', 'admin', 'assignedPic', 'attachments', 'statusLogs.actor']);

        return [
            ...self::summary($report),
            'description' => $report->description,
            'reporter_nim_nip' => $report->user?->nim_nip,
            'category_id' => $report->category_id,
            'room_id' => $report->room_id,
            'asset' => $report->asset ? [
                'id' => $report->asset->id,
                'asset_code' => $report->asset->asset_code,
                'name' => $report->asset->name,
                'brand' => $report->asset->brand,
                'category' => $report->asset->category?->name,
            ] : null,
            'admin' => $report->admin?->name,
            'rejection_reason' => $report->rejection_reason,
            'hold_reason' => $report->hold_reason,
            'repair_action' => $report->repair_action,
            'completed_on' => $report->completed_on?->toDateString(),
            'repair_cost' => $report->repair_cost,
            'vendor_name' => $report->vendor_name,
            'validated_at' => $report->validated_at?->toIso8601String(),
            'started_at' => $report->started_at?->toIso8601String(),
            'closed_at' => $report->closed_at?->toIso8601String(),
            'cancelled_at' => $report->cancelled_at?->toIso8601String(),
            'damage_photos' => self::photos($report, AttachmentType::Damage),
            'completion_photos' => self::photos($report, AttachmentType::Completion),
            'timeline' => $report->statusLogs->map(fn ($log) => [
                'id' => $log->id,
                'from_status' => $log->from_status,
                'to_status' => $log->to_status,
                'to_label' => \App\Enums\ReportStatus::from($log->to_status)->label(),
                'actor' => $log->actor?->name,
                'note' => $log->note,
                'created_at' => $log->created_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }

    /** @return list<array{id: int, url: string}> */
    private static function photos(Report $report, AttachmentType $type): array
    {
        return $report->attachments
            ->where('type', $type)
            ->sortBy('position')
            ->map(fn ($attachment) => ['id' => $attachment->id, 'url' => route('attachments.show', $attachment)])
            ->values()
            ->all();
    }
}
```

- [ ] **Step 7: Controllers.** `app/Http/Controllers/User/ReportController.php` (this task: `create`, `store`, `show`; Task 6 adds the rest)

```php
<?php

namespace App\Http\Controllers\User;

use App\Actions\Reports\SubmitReport;
use App\Enums\Priority;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReportRequest;
use App\Models\Building;
use App\Models\Category;
use App\Models\Report;
use App\Support\ReportPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function create(): Response
    {
        Gate::authorize('create', Report::class);

        return Inertia::render('reports/Create', $this->formOptions());
    }

    public function store(StoreReportRequest $request, SubmitReport $submit): RedirectResponse
    {
        Gate::authorize('create', Report::class);

        $report = $submit->handle(
            $request->user(),
            $request->safe()->except('photos'),
            $request->file('photos', []),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => "Laporan {$report->ticket_number} berhasil dikirim."]);

        return to_route('reports.show', $report);
    }

    public function show(Report $report): Response
    {
        Gate::authorize('view', $report);

        return Inertia::render('reports/Show', [
            'report' => ReportPresenter::detail($report),
            'can' => [
                'update' => Gate::allows('update', $report),
                'cancel' => Gate::allows('cancel', $report),
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function formOptions(): array
    {
        return [
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'buildings' => Building::query()->with(['rooms' => fn ($query) => $query->orderBy('code')])->orderBy('code')->get()
                ->map(fn (Building $building) => [
                    'id' => $building->id,
                    'code' => $building->code,
                    'name' => $building->name,
                    'rooms' => $building->rooms->map(fn ($room) => ['id' => $room->id, 'code' => $room->code, 'name' => $room->name])->values(),
                ]),
            'priorities' => collect(Priority::cases())->map(fn (Priority $priority) => ['value' => $priority->value, 'label' => $priority->label()]),
        ];
    }
}
```

`app/Http/Controllers/AttachmentController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Models\ReportAttachment;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class AttachmentController extends Controller
{
    public function __invoke(ReportAttachment $attachment): Response
    {
        Gate::authorize('view', $attachment->report);

        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

        return Storage::disk('local')->response($attachment->path, null, ['Cache-Control' => 'private, max-age=3600']);
    }
}
```

- [ ] **Step 8: Routes.** In `routes/web.php` add imports `use App\Http\Controllers\AttachmentController; use App\Http\Controllers\User\ReportController;`. Inside the `['auth','active']` group (outside the role groups) add:

```php
    Route::get('attachments/{attachment}', AttachmentController::class)->name('attachments.show');

    Route::middleware('role:user')->prefix('reports')->name('reports.')->group(function () {
        Route::get('create', [ReportController::class, 'create'])->name('create');
        Route::post('/', [ReportController::class, 'store'])->name('store');
        Route::get('{report}', [ReportController::class, 'show'])->name('show');
    });
```

(`create` is declared before `{report}` so it is not captured as an id.)

- [ ] **Step 9: Run the tests**

Run: `php artisan test tests/Feature/Reports tests/Feature/Notifications`
Expected: all pass. `config/inertia.php` has `testing.ensure_pages_exist => true`, so before running create two stub pages, `resources/js/pages/reports/Create.vue` and `resources/js/pages/reports/Show.vue`, each containing only `<template><div /></template>`. Tasks 7 and 8 replace them.

- [ ] **Step 10: Lint, types and commit**

Run: `composer lint:check && composer types:check`
Expected: clean (run `composer lint` to fix style).

```bash
git add -A
git commit -m "feat: submit damage reports with photos, tickets, logs and admin notifications"
```

---

### Task 6: My reports — list, edit, cancel (backend)

**Files:**
- Create: `app/Actions/Reports/{UpdateReport,CancelReport}.php`, `app/Http/Requests/UpdateReportRequest.php`, `tests/Feature/Reports/{MyReportsTest,UpdateReportTest,CancelReportTest,ReportVisibilityTest}.php`
- Modify: `app/Http/Controllers/User/ReportController.php`, `routes/web.php`

**Interfaces:**
- Produces: `UpdateReport::handle(Report $report, User $actor, array $data, array $newPhotos, array $removeIds): Report`, `CancelReport::handle(Report $report, User $actor): Report`; routes `reports.index`, `reports.edit`, `reports.update` (`PUT`), `reports.cancel` (`POST`). The update form posts with `_method=PUT`.
- Index props: `reports` (paginator of `ReportPresenter::summary`), `filters` `{status?, q?}`, `statuses` `[{value, label}]`.

- [ ] **Step 1: Write the failing tests.** `tests/Feature/Reports/MyReportsTest.php`

```php
<?php

use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\User;

beforeEach(function () {
    $this->me = User::factory()->create();
    $this->other = User::factory()->create();
});

test('a reporter only sees their own reports, newest first', function () {
    $old = Report::factory()->for($this->me)->create(['created_at' => now()->subDay()]);
    $new = Report::factory()->for($this->me)->create();
    Report::factory()->count(2)->for($this->other)->create();

    $this->actingAs($this->me)->get(route('reports.index'))
        ->assertInertia(fn ($page) => $page
            ->component('reports/Index')
            ->has('reports.data', 2)
            ->where('reports.data.0.id', $new->id)
            ->where('reports.data.1.id', $old->id));
});

test('the status filter narrows the list', function () {
    Report::factory()->for($this->me)->closed()->create();
    Report::factory()->for($this->me)->create();

    $this->actingAs($this->me)->get(route('reports.index', ['status' => 'Closed']))
        ->assertInertia(fn ($page) => $page->has('reports.data', 1)->where('reports.data.0.status', 'Closed'));
});

test('an unknown status filter is a validation error', function () {
    $this->actingAs($this->me)->get(route('reports.index', ['status' => 'Bogus']))->assertSessionHasErrors('status');
});

test('search matches ticket number and title', function () {
    $match = Report::factory()->for($this->me)->create(['title' => 'AC ruang dosen bocor']);
    Report::factory()->for($this->me)->create(['title' => 'Kursi patah']);

    $this->actingAs($this->me)->get(route('reports.index', ['q' => 'bocor']))
        ->assertInertia(fn ($page) => $page->has('reports.data', 1));

    $this->actingAs($this->me)->get(route('reports.index', ['q' => $match->ticket_number]))
        ->assertInertia(fn ($page) => $page->has('reports.data', 1)->where('reports.data.0.id', $match->id));
});

test('overdue reports are flagged', function () {
    Report::factory()->for($this->me)->assigned()->create(['sla_date' => now()->subDay()]);

    $this->actingAs($this->me)->get(route('reports.index'))
        ->assertInertia(fn ($page) => $page->where('reports.data.0.is_overdue', true));
});

test('the list is paginated at 10 per page', function () {
    Report::factory()->count(12)->for($this->me)->create();

    $this->actingAs($this->me)->get(route('reports.index'))
        ->assertInertia(fn ($page) => $page->has('reports.data', 10)->has('reports.links'));
});

test('admins and pics have no reporter list', function (string $state) {
    $this->actingAs(User::factory()->{$state}()->create())->get(route('reports.index'))->assertForbidden();
})->with(['admin', 'pic']);
```

`tests/Feature/Reports/ReportVisibilityTest.php`

```php
<?php

use App\Models\Report;
use App\Models\User;

test('the owner sees the detail with timeline and permissions', function () {
    $owner = User::factory()->create();
    $report = Report::factory()->for($owner)->create();
    app(\App\Actions\Reports\ReportTransitioner::class)->logCreated($report, $owner);

    $this->actingAs($owner)->get(route('reports.show', $report))
        ->assertInertia(fn ($page) => $page
            ->component('reports/Show')
            ->where('report.id', $report->id)
            ->has('report.timeline', 1)
            ->where('can.update', true)
            ->where('can.cancel', true));
});

test('another reporter gets 403', function () {
    $report = Report::factory()->create();

    $this->actingAs(User::factory()->create())->get(route('reports.show', $report))->assertForbidden();
});

test('a closed report shows the completion data to its owner and offers no edit', function () {
    $owner = User::factory()->create();
    $report = Report::factory()->for($owner)->closed()->create();

    $this->actingAs($owner)->get(route('reports.show', $report))
        ->assertInertia(fn ($page) => $page
            ->where('report.status', 'Closed')
            ->where('report.repair_action', 'Ganti lampu proyektor.')
            ->where('report.vendor_name', 'CV Teknik Jaya')
            ->where('can.update', false)
            ->where('can.cancel', false));
});

test('a rejected report shows the reason', function () {
    $owner = User::factory()->create();
    $report = Report::factory()->for($owner)->rejected()->create();

    $this->actingAs($owner)->get(route('reports.show', $report))
        ->assertInertia(fn ($page) => $page->where('report.rejection_reason', 'Laporan duplikat dengan tiket lain.'));
});
```

`tests/Feature/Reports/UpdateReportTest.php`

```php
<?php

use App\Enums\AttachmentType;
use App\Models\Category;
use App\Models\Report;
use App\Models\ReportAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ReportInput;

beforeEach(function () {
    Storage::fake('local');
    $this->owner = User::factory()->create();
    $this->report = Report::factory()->for($this->owner)->create(['title' => 'Judul lama']);
    $this->photo = ReportAttachment::factory()->for($this->report)->create(['type' => AttachmentType::Damage, 'path' => 'reports/1/damage/lama.jpg']);
    Storage::disk('local')->put($this->photo->path, 'x');
});

function updatePayload(array $overrides = []): array
{
    $input = ReportInput::valid($overrides);
    unset($input['photos']);

    return array_merge($input, ['_method' => 'PUT'], $overrides);
}

test('the edit page is shown to the owner while pending', function () {
    $this->actingAs($this->owner)->get(route('reports.edit', $this->report))
        ->assertInertia(fn ($page) => $page->component('reports/Edit')->where('report.id', $this->report->id)->has('categories'));
});

test('the owner updates a pending report and the edit is logged', function () {
    $category = Category::factory()->create();

    $this->actingAs($this->owner)->post(route('reports.update', $this->report), updatePayload(['title' => 'Judul baru', 'category_id' => $category->id]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('reports.show', $this->report));

    expect($this->report->fresh()->title)->toBe('Judul baru')
        ->and($this->report->fresh()->category_id)->toBe($category->id)
        ->and($this->report->fresh()->status->value)->toBe('Pending');

    expect($this->report->statusLogs()->get()->last()->note)->toBe('Laporan diubah');
});

test('new photos can be added up to three in total', function () {
    $this->actingAs($this->owner)->post(route('reports.update', $this->report), [
        ...updatePayload(),
        'photos' => [UploadedFile::fake()->image('b.jpg'), UploadedFile::fake()->image('c.jpg')],
    ])->assertSessionHasNoErrors();

    expect($this->report->damagePhotos()->count())->toBe(3);

    $this->actingAs($this->owner)->post(route('reports.update', $this->report), [
        ...updatePayload(),
        'photos' => [UploadedFile::fake()->image('d.jpg')],
    ])->assertSessionHasErrors(['photos' => 'Maksimal 3 foto.']);
});

test('an existing photo can be replaced', function () {
    $this->actingAs($this->owner)->post(route('reports.update', $this->report), [
        ...updatePayload(),
        'remove_photo_ids' => [$this->photo->id],
        'photos' => [UploadedFile::fake()->image('baru.jpg')],
    ])->assertSessionHasNoErrors();

    expect(ReportAttachment::find($this->photo->id))->toBeNull();
    Storage::disk('local')->assertMissing('reports/1/damage/lama.jpg');
    expect($this->report->damagePhotos()->count())->toBe(1);
});

test('removing every photo without adding one is refused and the photo stays', function () {
    $this->actingAs($this->owner)->post(route('reports.update', $this->report), [
        ...updatePayload(),
        'remove_photo_ids' => [$this->photo->id],
    ])->assertSessionHasErrors(['photos' => 'Minimal 1 foto kerusakan.']);

    expect(ReportAttachment::find($this->photo->id))->not->toBeNull();
    Storage::disk('local')->assertExists($this->photo->path);
});

test('another report’s photo ids can not be removed through this report', function () {
    $foreign = ReportAttachment::factory()->create(['path' => 'reports/2/damage/lain.jpg']);
    Storage::disk('local')->put($foreign->path, 'x');

    $this->actingAs($this->owner)->post(route('reports.update', $this->report), [
        ...updatePayload(),
        'remove_photo_ids' => [$foreign->id],
        'photos' => [UploadedFile::fake()->image('b.jpg')],
    ]);

    expect(ReportAttachment::find($foreign->id))->not->toBeNull();
});

test('a stale form is refused once the report is no longer pending', function () {
    $this->report->update(['status' => 'Assigned']);

    $this->actingAs($this->owner)->post(route('reports.update', $this->report), updatePayload(['title' => 'Terlambat']))->assertForbidden();
    $this->actingAs($this->owner)->get(route('reports.edit', $this->report))->assertForbidden();

    expect($this->report->fresh()->title)->toBe('Judul lama');
});

test('only the owner can edit', function () {
    $this->actingAs(User::factory()->create())->post(route('reports.update', $this->report), updatePayload())->assertForbidden();
    $this->actingAs(User::factory()->admin()->create())->post(route('reports.update', $this->report), updatePayload())->assertForbidden();
});

test('validation rules of the create form apply to edits too', function () {
    $this->actingAs($this->owner)->post(route('reports.update', $this->report), updatePayload(['title' => '', 'description' => 'x']))
        ->assertSessionHasErrors(['title', 'description']);
});
```

`tests/Feature/Reports/CancelReportTest.php`

```php
<?php

use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\User;

test('the owner cancels a pending report', function () {
    $owner = User::factory()->create();
    $report = Report::factory()->for($owner)->create();

    $this->actingAs($owner)->post(route('reports.cancel', $report))->assertRedirect(route('reports.show', $report));

    $report->refresh();
    expect($report->status)->toBe(ReportStatus::Cancelled)
        ->and($report->cancelled_at)->not->toBeNull();

    $log = $report->statusLogs()->sole();
    expect($log->from_status)->toBe('Pending')->and($log->to_status)->toBe('Cancelled')->and($log->actor_id)->toBe($owner->id);
});

test('cancelling twice is refused', function () {
    $owner = User::factory()->create();
    $report = Report::factory()->for($owner)->create();

    $this->actingAs($owner)->post(route('reports.cancel', $report));
    $this->actingAs($owner)->post(route('reports.cancel', $report))->assertForbidden();

    expect($report->statusLogs()->count())->toBe(1);
});

test('a report that is already being processed can not be cancelled', function () {
    $owner = User::factory()->create();
    $report = Report::factory()->for($owner)->assigned()->create();

    $this->actingAs($owner)->post(route('reports.cancel', $report))->assertForbidden();

    expect($report->fresh()->status)->toBe(ReportStatus::Assigned);
});

test('nobody but the owner can cancel', function () {
    $report = Report::factory()->create();

    $this->actingAs(User::factory()->create())->post(route('reports.cancel', $report))->assertForbidden();
    $this->actingAs(User::factory()->admin()->create())->post(route('reports.cancel', $report))->assertForbidden();
});
```

- [ ] **Step 2: Run to verify they fail**

Run: `php artisan test tests/Feature/Reports/MyReportsTest.php tests/Feature/Reports/UpdateReportTest.php tests/Feature/Reports/CancelReportTest.php tests/Feature/Reports/ReportVisibilityTest.php`
Expected: FAIL (`Route [reports.index] not defined`).

- [ ] **Step 3: Actions.** `app/Actions/Reports/UpdateReport.php`

```php
<?php

namespace App\Actions\Reports;

use App\Enums\AttachmentType;
use App\Models\Report;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

final class UpdateReport
{
    public function __construct(
        private readonly ReportTransitioner $transitioner,
        private readonly AttachmentStore $attachments,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, UploadedFile>  $newPhotos
     * @param  array<int, int|string>  $removeIds
     */
    public function handle(Report $report, User $actor, array $data, array $newPhotos, array $removeIds): Report
    {
        return DB::transaction(function () use ($report, $actor, $data, $newPhotos, $removeIds) {
            $report->update($data);

            $this->attachments->remove($report, AttachmentType::Damage, $removeIds);
            $this->attachments->add($report, AttachmentType::Damage, $newPhotos);

            $this->transitioner->note($report, $actor, 'Laporan diubah');

            return $report;
        });
    }
}
```

`app/Actions/Reports/CancelReport.php`

```php
<?php

namespace App\Actions\Reports;

use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class CancelReport
{
    public function __construct(private readonly ReportTransitioner $transitioner) {}

    public function handle(Report $report, User $actor): Report
    {
        return DB::transaction(fn () => $this->transitioner->transition(
            $report,
            ReportStatus::Cancelled,
            $actor,
            'Dibatalkan oleh pelapor',
            ['cancelled_at' => now()],
        ));
    }
}
```

- [ ] **Step 4: `UpdateReportRequest`** `app/Http/Requests/UpdateReportRequest.php`

```php
<?php

namespace App\Http\Requests;

use Illuminate\Validation\Validator;

class UpdateReportRequest extends StoreReportRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'photos' => ['nullable', 'array', 'max:'.config('siperkasa.photos.max')],
            'remove_photo_ids' => ['nullable', 'array'],
            'remove_photo_ids.*' => ['integer'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $report = $this->route('report');
            $removed = collect($this->input('remove_photo_ids', []))->map(fn ($id) => (int) $id)->all();

            $kept = $report->damagePhotos()->whereNotIn('id', $removed)->count();
            $total = $kept + count($this->file('photos', []));

            if ($total < config('siperkasa.photos.min')) {
                $validator->errors()->add('photos', 'Minimal 1 foto kerusakan.');
            } elseif ($total > config('siperkasa.photos.max')) {
                $validator->errors()->add('photos', 'Maksimal 3 foto.');
            }
        }];
    }
}
```

When `photos` is present but `max:3` is violated by the field rule alone, the message from the parent (`photos.max`) is used; the `after()` check covers the combined total.

- [ ] **Step 5: Extend the controller.** Replace `app/Http/Controllers/User/ReportController.php` with the full version (adds `index`, `edit`, `update`, `cancel`):

```php
<?php

namespace App\Http\Controllers\User;

use App\Actions\Reports\CancelReport;
use App\Actions\Reports\SubmitReport;
use App\Actions\Reports\UpdateReport;
use App\Enums\Priority;
use App\Enums\ReportStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReportRequest;
use App\Http\Requests\UpdateReportRequest;
use App\Models\Building;
use App\Models\Category;
use App\Models\Report;
use App\Support\ReportPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('create', Report::class); // reporters only

        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(ReportStatus::class)],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $reports = Report::query()
            ->visibleTo($request->user())
            ->with(['category:id,name', 'room.building:id,name', 'assignedPic:id,name', 'user:id,name'])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['q'] ?? null, function ($query, string $q) {
                $like = '%'.addcslashes($q, '%_\\').'%';
                $query->where(fn ($inner) => $inner->where('ticket_number', 'like', $like)->orWhere('title', 'like', $like));
            })
            ->latest('created_at')
            ->latest('id')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Report $report) => ReportPresenter::summary($report));

        return Inertia::render('reports/Index', [
            'reports' => $reports,
            'filters' => $filters,
            'statuses' => collect(ReportStatus::cases())->map(fn (ReportStatus $status) => ['value' => $status->value, 'label' => $status->label()]),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Report::class);

        return Inertia::render('reports/Create', $this->formOptions());
    }

    public function store(StoreReportRequest $request, SubmitReport $submit): RedirectResponse
    {
        Gate::authorize('create', Report::class);

        $report = $submit->handle(
            $request->user(),
            $request->safe()->except('photos'),
            $request->file('photos', []),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => "Laporan {$report->ticket_number} berhasil dikirim."]);

        return to_route('reports.show', $report);
    }

    public function show(Report $report): Response
    {
        Gate::authorize('view', $report);

        return Inertia::render('reports/Show', [
            'report' => ReportPresenter::detail($report),
            'can' => [
                'update' => Gate::allows('update', $report),
                'cancel' => Gate::allows('cancel', $report),
            ],
        ]);
    }

    public function edit(Report $report): Response
    {
        Gate::authorize('update', $report);

        return Inertia::render('reports/Edit', [
            ...$this->formOptions(),
            'report' => ReportPresenter::detail($report),
        ]);
    }

    public function update(UpdateReportRequest $request, Report $report, UpdateReport $update): RedirectResponse
    {
        Gate::authorize('update', $report);

        $update->handle(
            $report,
            $request->user(),
            $request->safe()->except(['photos', 'remove_photo_ids']),
            $request->file('photos', []),
            $request->input('remove_photo_ids', []),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Laporan diperbarui.']);

        return to_route('reports.show', $report);
    }

    public function cancel(Request $request, Report $report, CancelReport $cancel): RedirectResponse
    {
        Gate::authorize('cancel', $report);

        $cancel->handle($report, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Laporan dibatalkan.']);

        return to_route('reports.show', $report);
    }

    /** @return array<string, mixed> */
    private function formOptions(): array
    {
        return [
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'buildings' => Building::query()->with(['rooms' => fn ($query) => $query->orderBy('code')])->orderBy('code')->get()
                ->map(fn (Building $building) => [
                    'id' => $building->id,
                    'code' => $building->code,
                    'name' => $building->name,
                    'rooms' => $building->rooms->map(fn ($room) => ['id' => $room->id, 'code' => $room->code, 'name' => $room->name])->values(),
                ]),
            'priorities' => collect(Priority::cases())->map(fn (Priority $priority) => ['value' => $priority->value, 'label' => $priority->label()]),
        ];
    }
}
```

Important: `UpdateReportRequest::after()` runs before `Gate::authorize('update')` in the controller, and `FormRequest` authorization is not defined, so a stale form (status no longer `Pending`) first passes validation, then `Gate::authorize` returns 403 — no data is changed either way. The test for a stale form posts a valid payload without photos, and the combined-photo check passes because the old photo remains.

- [ ] **Step 6: Routes.** Replace the `reports` group in `routes/web.php`:

```php
    Route::middleware('role:user')->prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::get('create', [ReportController::class, 'create'])->name('create');
        Route::post('/', [ReportController::class, 'store'])->name('store');
        Route::get('{report}', [ReportController::class, 'show'])->name('show');
        Route::get('{report}/edit', [ReportController::class, 'edit'])->name('edit');
        Route::put('{report}', [ReportController::class, 'update'])->name('update');
        Route::post('{report}/cancel', [ReportController::class, 'cancel'])->name('cancel');
    });
```

The update tests post with `_method => PUT` to `route('reports.update')`, which Laravel treats as `PUT`.

- [ ] **Step 7: Run the tests**

Run: `php artisan test`
Expected: all pass. Before running, create the stub pages `resources/js/pages/reports/Index.vue` and `resources/js/pages/reports/Edit.vue`, each containing only `<template><div /></template>` (Inertia checks that page files exist). Tasks 7 and 8 replace them.

- [ ] **Step 8: Lint, types and commit**

Run: `composer lint:check && composer types:check`
Expected: clean.

```bash
git add -A
git commit -m "feat: my reports list, edit and cancel while pending"
```

---

### Task 7: Reporter form UI (photo uploader, asset search, create and edit pages)

**Files:**
- Create: `resources/js/lib/compressImage.ts`, `resources/js/components/{PhotoUploader,AssetCombobox,ReportForm}.vue`, `resources/js/pages/reports/{Create,Edit}.vue`
- Modify: `resources/js/composables/useRoleNav.ts`

**Interfaces:**
- Produces:
  - `compressImage(file: File, maxSide = 1600, quality = 0.8): Promise<File>` — resizes and re-encodes to WebP; returns the original file if the browser cannot.
  - `PhotoUploader` — `v-model` `File[]` (new files), props `existing: {id; url}[]`, `removedIds: number[]` (`@update:removedIds`), `max` (default 3), `error`. Plan 4 reuses it for completion photos.
  - `AssetCombobox` — `v-model` `AssetOption | null`; exports type `AssetOption`.
  - `ReportForm` — props `mode: 'create' | 'edit'`, `categories`, `buildings`, `priorities`, optional `report`.

- [ ] **Step 1: `compressImage.ts`**

```ts
/**
 * Downscales an image so its longest side is at most `maxSide` px and re-encodes it as WebP.
 * Falls back to the original file when the browser cannot decode or encode it.
 */
export async function compressImage(
    file: File,
    maxSide = 1600,
    quality = 0.8,
): Promise<File> {
    if (!file.type.startsWith('image/') || typeof createImageBitmap !== 'function') {
        return file;
    }

    try {
        const bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
        const scale = Math.min(1, maxSide / Math.max(bitmap.width, bitmap.height));
        const width = Math.round(bitmap.width * scale);
        const height = Math.round(bitmap.height * scale);

        const canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;

        const context = canvas.getContext('2d');
        if (!context) return file;

        context.drawImage(bitmap, 0, 0, width, height);
        bitmap.close();

        const blob = await new Promise<Blob | null>((resolve) =>
            canvas.toBlob(resolve, 'image/webp', quality),
        );

        // Keep the original when re-encoding does not make it smaller.
        if (!blob || blob.size >= file.size) {
            return file;
        }

        return new File([blob], renamed(file.name), { type: 'image/webp' });
    } catch {
        return file;
    }
}

function renamed(name: string): string {
    return `${name.replace(/\.[^.]+$/, '')}.webp`;
}
```

- [ ] **Step 2: `PhotoUploader.vue`**

```vue
<script setup lang="ts">
import { Camera, ImagePlus, X } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { compressImage } from '@/lib/compressImage';

type Existing = { id: number; url: string };

const props = withDefaults(
    defineProps<{
        modelValue: File[];
        existing?: Existing[];
        removedIds?: number[];
        max?: number;
        error?: string;
    }>(),
    { existing: () => [], removedIds: () => [], max: 3 },
);

const emit = defineEmits<{
    'update:modelValue': [files: File[]];
    'update:removedIds': [ids: number[]];
}>();

const busy = ref(false);
const keptExisting = computed(() => props.existing.filter((photo) => !props.removedIds.includes(photo.id)));
const total = computed(() => keptExisting.value.length + props.modelValue.length);
const remaining = computed(() => props.max - total.value);

const previews = ref<{ file: File; url: string }[]>([]);

watch(
    () => props.modelValue,
    (files) => {
        const next = files.map(
            (file) => previews.value.find((preview) => preview.file === file) ?? { file, url: URL.createObjectURL(file) },
        );
        previews.value.filter((preview) => !next.includes(preview)).forEach((preview) => URL.revokeObjectURL(preview.url));
        previews.value = next;
    },
    { immediate: true },
);

onBeforeUnmount(() => previews.value.forEach((preview) => URL.revokeObjectURL(preview.url)));

async function add(event: Event) {
    const input = event.target as HTMLInputElement;
    const picked = Array.from(input.files ?? []).slice(0, Math.max(remaining.value, 0));
    input.value = '';

    if (picked.length === 0) return;

    busy.value = true;
    try {
        const compressed = await Promise.all(picked.map((file) => compressImage(file)));
        emit('update:modelValue', [...props.modelValue, ...compressed]);
    } finally {
        busy.value = false;
    }
}

const removeNew = (file: File) => emit('update:modelValue', props.modelValue.filter((item) => item !== file));
const removeExisting = (id: number) => emit('update:removedIds', [...props.removedIds, id]);
</script>

<template>
    <div class="grid gap-3">
        <ul v-if="keptExisting.length || previews.length" class="grid grid-cols-3 gap-2">
            <li v-for="photo in keptExisting" :key="`e${photo.id}`" class="relative aspect-square overflow-hidden rounded-lg border">
                <img :src="photo.url" alt="Foto yang sudah diunggah" class="size-full object-cover" />
                <button
                    type="button"
                    class="absolute top-1 right-1 inline-flex size-11 items-center justify-center rounded-full bg-background/90 shadow focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    aria-label="Hapus foto"
                    @click="removeExisting(photo.id)"
                >
                    <X class="size-4" />
                </button>
            </li>
            <li v-for="preview in previews" :key="preview.url" class="relative aspect-square overflow-hidden rounded-lg border">
                <img :src="preview.url" alt="Pratinjau foto baru" class="size-full object-cover" />
                <button
                    type="button"
                    class="absolute top-1 right-1 inline-flex size-11 items-center justify-center rounded-full bg-background/90 shadow focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    aria-label="Hapus foto"
                    @click="removeNew(preview.file)"
                >
                    <X class="size-4" />
                </button>
            </li>
        </ul>

        <div class="flex flex-wrap gap-2">
            <Button as-child variant="outline" class="h-11" :class="{ 'pointer-events-none opacity-50': remaining <= 0 || busy }">
                <label class="cursor-pointer">
                    <Camera class="size-4" /> Ambil foto
                    <input type="file" accept="image/*" capture="environment" class="sr-only" :disabled="remaining <= 0 || busy" @change="add" />
                </label>
            </Button>
            <Button as-child variant="outline" class="h-11" :class="{ 'pointer-events-none opacity-50': remaining <= 0 || busy }">
                <label class="cursor-pointer">
                    <ImagePlus class="size-4" /> Pilih dari galeri
                    <input type="file" accept="image/*" multiple class="sr-only" :disabled="remaining <= 0 || busy" @change="add" />
                </label>
            </Button>
        </div>

        <p class="text-xs text-muted-foreground" aria-live="polite">
            {{ busy ? 'Memproses foto…' : `${total} dari maksimal ${max} foto. Minimal 1 foto.` }}
        </p>
        <p v-if="error" class="text-sm text-destructive" role="alert">{{ error }}</p>
    </div>
</template>
```

- [ ] **Step 3: `AssetCombobox.vue`**

```vue
<script lang="ts">
export type AssetOption = {
    id: number;
    asset_code: string;
    name: string;
    brand: string | null;
    category: string | null;
};
</script>

<script setup lang="ts">
import { X } from '@lucide/vue';
import { onBeforeUnmount, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { search } from '@/routes/assets';

const model = defineModel<AssetOption | null>({ default: null });

const query = ref('');
const results = ref<AssetOption[]>([]);
const open = ref(false);
const active = ref(-1);
const loading = ref(false);
let timer: ReturnType<typeof setTimeout> | undefined;
let sequence = 0;

function onInput() {
    clearTimeout(timer);

    if (query.value.trim().length < 2) {
        results.value = [];
        open.value = false;
        return;
    }

    timer = setTimeout(load, 300);
}

async function load() {
    const current = ++sequence;
    loading.value = true;

    try {
        const response = await fetch(search.url({ query: { q: query.value.trim() } }), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        if (!response.ok) throw new Error(String(response.status));

        const json = (await response.json()) as { data: AssetOption[] };
        if (current !== sequence) return;

        results.value = json.data;
        active.value = -1;
        open.value = true;
    } catch {
        results.value = [];
    } finally {
        if (current === sequence) loading.value = false;
    }
}

function choose(option: AssetOption) {
    model.value = option;
    query.value = '';
    results.value = [];
    open.value = false;
}

function onKeydown(event: KeyboardEvent) {
    if (!open.value) return;

    if (event.key === 'ArrowDown') {
        event.preventDefault();
        active.value = Math.min(active.value + 1, results.value.length - 1);
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        active.value = Math.max(active.value - 1, 0);
    } else if (event.key === 'Enter' && active.value >= 0) {
        event.preventDefault();
        choose(results.value[active.value]);
    } else if (event.key === 'Escape') {
        open.value = false;
    }
}

onBeforeUnmount(() => clearTimeout(timer));
</script>

<template>
    <div class="relative">
        <div v-if="model" class="flex items-center justify-between gap-2 rounded-md border p-3">
            <div class="min-w-0">
                <p class="truncate font-medium">{{ model.name }}</p>
                <p class="text-sm text-muted-foreground">{{ model.asset_code }}</p>
            </div>
            <Button type="button" variant="outline" size="icon" class="size-11 shrink-0" aria-label="Hapus aset terpilih" @click="model = null">
                <X class="size-4" />
            </Button>
        </div>

        <template v-else>
            <Input
                v-model="query"
                class="h-11"
                role="combobox"
                aria-label="Cari aset"
                aria-autocomplete="list"
                aria-controls="asset-listbox"
                :aria-expanded="open"
                :aria-activedescendant="active >= 0 ? `asset-option-${active}` : undefined"
                placeholder="Cari kode atau nama aset (opsional)"
                autocomplete="off"
                @input="onInput"
                @keydown="onKeydown"
            />
            <ul
                v-show="open"
                id="asset-listbox"
                role="listbox"
                class="absolute z-20 mt-1 max-h-64 w-full overflow-auto rounded-md border bg-popover shadow-md"
            >
                <li v-if="results.length === 0 && !loading" class="p-3 text-sm text-muted-foreground">Aset tidak ditemukan.</li>
                <li
                    v-for="(option, index) in results"
                    :id="`asset-option-${index}`"
                    :key="option.id"
                    role="option"
                    :aria-selected="index === active"
                    class="min-h-11 cursor-pointer px-3 py-2 text-sm"
                    :class="index === active ? 'bg-accent' : 'hover:bg-accent'"
                    @mousedown.prevent="choose(option)"
                >
                    <span class="block font-medium">{{ option.name }}</span>
                    <span class="block text-muted-foreground">{{ option.asset_code }}{{ option.brand ? ` · ${option.brand}` : '' }}</span>
                </li>
            </ul>
        </template>
    </div>
</template>
```

- [ ] **Step 4: `ReportForm.vue`**

```vue
<script setup lang="ts">
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AssetCombobox from '@/components/AssetCombobox.vue';
import type { AssetOption } from '@/components/AssetCombobox.vue';
import InputError from '@/components/InputError.vue';
import PhotoUploader from '@/components/PhotoUploader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import { index, show, store, update } from '@/routes/reports';

type Building = { id: number; code: string; name: string; rooms: { id: number; code: string; name: string }[] };
type ReportData = {
    id: number;
    title: string;
    category_id: number;
    room_id: number;
    description: string;
    user_priority: string;
    asset: AssetOption | null;
    damage_photos: { id: number; url: string }[];
};

const props = defineProps<{
    mode: 'create' | 'edit';
    categories: { id: number; name: string }[];
    buildings: Building[];
    priorities: { value: string; label: string }[];
    report?: ReportData;
}>();

const me = computed(() => usePage().props.auth.user);

const initialBuilding = props.report
    ? (props.buildings.find((b) => b.rooms.some((r) => r.id === props.report!.room_id))?.id ?? '')
    : '';

const buildingId = ref<string | number>(initialBuilding);
const rooms = computed(() => props.buildings.find((b) => b.id === Number(buildingId.value))?.rooms ?? []);

const form = useForm({
    title: props.report?.title ?? '',
    category_id: (props.report?.category_id ?? '') as string | number,
    room_id: (props.report?.room_id ?? '') as string | number,
    asset: (props.report?.asset ?? null) as AssetOption | null,
    description: props.report?.description ?? '',
    user_priority: props.report?.user_priority ?? 'Medium',
    photos: [] as File[],
    remove_photo_ids: [] as number[],
});

watch(buildingId, () => {
    if (!rooms.value.some((room) => room.id === Number(form.room_id))) form.room_id = '';
});

const photoError = computed(() => form.errors.photos ?? form.errors['photos.0' as keyof typeof form.errors]);

function submit() {
    form.transform((data) => {
        const { asset, ...rest } = data;
        const payload = { ...rest, asset_id: asset?.id ?? null };

        return props.mode === 'edit' ? { ...payload, _method: 'put' } : payload;
    });

    const url = props.mode === 'edit' ? update.url(props.report!.id) : store.url();
    form.post(url, { forceFormData: true, preserveScroll: true });
}
</script>

<template>
    <form class="grid gap-5 pb-4" @submit.prevent="submit">
        <section class="grid gap-3 rounded-xl border bg-muted/40 p-3 text-sm" aria-label="Data pelapor">
            <div><span class="text-muted-foreground">Pelapor</span> <span class="font-medium">{{ me.name }}</span></div>
            <div><span class="text-muted-foreground">NIM/NIP</span> <span class="font-medium">{{ me.nim_nip }}</span></div>
        </section>

        <div class="grid gap-2">
            <Label for="title">Judul laporan</Label>
            <Input id="title" v-model="form.title" class="h-11" maxlength="120" placeholder="Contoh: Proyektor tidak menyala" required />
            <InputError :message="form.errors.title" />
        </div>

        <div class="grid gap-2">
            <Label for="category">Kategori</Label>
            <NativeSelect id="category" v-model="form.category_id" required>
                <option value="" disabled>Pilih kategori</option>
                <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option>
            </NativeSelect>
            <InputError :message="form.errors.category_id" />
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="building">Gedung</Label>
                <NativeSelect id="building" v-model="buildingId" required>
                    <option value="" disabled>Pilih gedung</option>
                    <option v-for="building in buildings" :key="building.id" :value="building.id">{{ building.name }}</option>
                </NativeSelect>
            </div>
            <div class="grid gap-2">
                <Label for="room">Ruang</Label>
                <NativeSelect id="room" v-model="form.room_id" :disabled="rooms.length === 0" required>
                    <option value="" disabled>{{ buildingId ? 'Pilih ruang' : 'Pilih gedung dulu' }}</option>
                    <option v-for="room in rooms" :key="room.id" :value="room.id">{{ room.name }}</option>
                </NativeSelect>
                <InputError :message="form.errors.room_id" />
            </div>
        </div>

        <div class="grid gap-2">
            <Label>Aset terkait (opsional)</Label>
            <AssetCombobox v-model="form.asset" />
            <InputError :message="form.errors.asset_id" />
        </div>

        <div class="grid gap-2">
            <Label for="description">Deskripsi kerusakan</Label>
            <Textarea id="description" v-model="form.description" :rows="5" maxlength="2000" placeholder="Jelaskan apa yang rusak dan sejak kapan." required />
            <InputError :message="form.errors.description" />
        </div>

        <div class="grid gap-2">
            <Label for="priority">Urgensi menurut Anda</Label>
            <NativeSelect id="priority" v-model="form.user_priority" required>
                <option v-for="priority in priorities" :key="priority.value" :value="priority.value">{{ priority.label }}</option>
            </NativeSelect>
            <InputError :message="form.errors.user_priority" />
        </div>

        <div class="grid gap-2">
            <Label>Foto kerusakan</Label>
            <PhotoUploader
                v-model="form.photos"
                v-model:removed-ids="form.remove_photo_ids"
                :existing="report?.damage_photos ?? []"
                :error="photoError"
            />
        </div>

        <div class="sticky bottom-14 z-30 -mx-4 flex gap-2 border-t bg-background p-4 md:bottom-0">
            <Button type="button" variant="outline" class="h-11" :disabled="form.processing" @click="router.visit(props.report ? show.url(props.report.id) : index.url())">
                Batal
            </Button>
            <Button type="submit" class="h-11 flex-1" :disabled="form.processing" data-test="submit-report">
                {{ form.processing ? 'Mengirim…' : mode === 'edit' ? 'Simpan perubahan' : 'Kirim laporan' }}
            </Button>
        </div>
    </form>
</template>
```

- [ ] **Step 5: Pages.** `resources/js/pages/reports/Create.vue`

```vue
<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/components/PageHeader.vue';
import ReportForm from '@/components/ReportForm.vue';
import { create, index } from '@/routes/reports';

defineProps<{
    categories: { id: number; name: string }[];
    buildings: { id: number; code: string; name: string; rooms: { id: number; code: string; name: string }[] }[];
    priorities: { value: string; label: string }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Laporan Saya', href: index() },
            { title: 'Buat Laporan', href: create() },
        ],
    },
});
</script>

<template>
    <Head title="Buat Laporan" />

    <div class="mx-auto flex w-full max-w-2xl flex-col gap-4 p-4">
        <PageHeader title="Buat Laporan" description="Laporkan kerusakan aset. Tim akan memvalidasi dan menugaskan PIC." />
        <ReportForm mode="create" :categories="categories" :buildings="buildings" :priorities="priorities" />
    </div>
</template>
```

`resources/js/pages/reports/Edit.vue`

```vue
<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/components/PageHeader.vue';
import ReportForm from '@/components/ReportForm.vue';
import { index } from '@/routes/reports';

defineProps<{
    categories: { id: number; name: string }[];
    buildings: { id: number; code: string; name: string; rooms: { id: number; code: string; name: string }[] }[];
    priorities: { value: string; label: string }[];
    report: {
        id: number;
        ticket_number: string;
        title: string;
        category_id: number;
        room_id: number;
        description: string;
        user_priority: string;
        asset: { id: number; asset_code: string; name: string; brand: string | null; category: string | null } | null;
        damage_photos: { id: number; url: string }[];
    };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Laporan Saya', href: index() }] },
});
</script>

<template>
    <Head title="Ubah Laporan" />

    <div class="mx-auto flex w-full max-w-2xl flex-col gap-4 p-4">
        <PageHeader :title="`Ubah ${report.ticket_number}`" description="Laporan hanya bisa diubah selama masih menunggu validasi." />
        <ReportForm mode="edit" :categories="categories" :buildings="buildings" :priorities="priorities" :report="report" />
    </div>
</template>
```

- [ ] **Step 6: Nav entries for reporters.** In `useRoleNav.ts` import `ClipboardList, FilePlus` from `@lucide/vue` and `{ create as reportsCreate, index as reportsIndex } from '@/routes/reports'`; replace `userNav`:

```ts
const userNav: NavItem[] = [
    { title: 'Beranda', href: userDashboard(), icon: Home },
    { title: 'Laporan Saya', href: reportsIndex(), icon: ClipboardList },
    { title: 'Buat Laporan', href: reportsCreate(), icon: FilePlus },
];
```

- [ ] **Step 7: Verify**

Run: `php artisan wayfinder:generate && npm run types:check && npm run check && npm run build`
Expected: all succeed.

- [ ] **Step 8: Manual check on a phone-sized window (360 px)**

Run `composer dev`, log in as `2010001` / `password`, open "Buat Laporan".
Expected: choosing a building filters the rooms; the asset search shows matches after 2 characters and a 300 ms pause; taking or choosing a 5 MB photo yields a preview well below 2 MB (open DevTools → Network on submit to confirm the uploaded size); a 4th photo is not accepted; the submit bar stays above the bottom nav; submitting redirects to the report page with a success toast.

- [ ] **Step 9: Commit**

```bash
git add -A
git commit -m "feat: reporter form with photo compression and asset search"
```

---

### Task 8: My reports pages (list, detail, timeline)

**Files:**
- Create: `resources/js/components/Timeline.vue`, `resources/js/pages/reports/{Index,Show}.vue`

**Interfaces:**
- Produces: `Timeline` props `{ items: { id: number; to_status: string; to_label: string; actor: string | null; note: string | null; created_at: string }[] }`. Plan 4 reuses `Timeline` on the admin and PIC detail pages.

- [ ] **Step 1: `Timeline.vue`**

```vue
<script setup lang="ts">
import StatusBadge from '@/components/StatusBadge.vue';
import { formatDateTime } from '@/lib/format';

defineProps<{
    items: {
        id: number;
        to_status: string;
        to_label: string;
        actor: string | null;
        note: string | null;
        created_at: string;
    }[];
}>();
</script>

<template>
    <ol class="relative grid gap-4 border-l border-border pl-5" aria-label="Riwayat status">
        <li v-for="item in items" :key="item.id" class="relative">
            <span class="absolute top-1.5 -left-[1.6rem] size-3 rounded-full border-2 border-background bg-primary" aria-hidden="true" />
            <div class="flex flex-wrap items-center gap-2">
                <StatusBadge :status="item.to_status" />
                <time class="text-xs text-muted-foreground" :datetime="item.created_at">{{ formatDateTime(item.created_at) }}</time>
            </div>
            <p v-if="item.actor" class="mt-1 text-sm text-muted-foreground">oleh {{ item.actor }}</p>
            <p v-if="item.note" class="mt-1 text-sm">{{ item.note }}</p>
        </li>
    </ol>
</template>
```

- [ ] **Step 2: `reports/Index.vue`**

```vue
<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { ref } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatDate } from '@/lib/format';
import { create, index, show } from '@/routes/reports';

type Row = {
    id: number;
    ticket_number: string;
    title: string;
    status: string;
    is_overdue: boolean;
    created_at: string;
    category: string | null;
    room: string | null;
    building: string | null;
};

const props = defineProps<{
    reports: { data: Row[]; links: { url: string | null; label: string; active: boolean }[] };
    filters: { status?: string; q?: string };
    statuses: { value: string; label: string }[];
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Laporan Saya', href: index() }] },
});

const q = ref(props.filters.q ?? '');

function filter(status?: string) {
    router.get(index.url(), { status: status || undefined, q: q.value || undefined }, { preserveState: true, replace: true });
}
</script>

<template>
    <Head title="Laporan Saya" />

    <div class="flex flex-col gap-4 p-4">
        <PageHeader title="Laporan Saya" description="Pantau status semua laporan yang Anda buat.">
            <Button as-child class="h-11"><Link :href="create()"><Plus class="size-4" /> Buat laporan</Link></Button>
        </PageHeader>

        <form class="flex gap-2" @submit.prevent="filter(filters.status)">
            <Input v-model="q" class="h-11" placeholder="Cari nomor tiket atau judul" aria-label="Cari laporan" />
            <Button type="submit" variant="outline" class="h-11">Cari</Button>
        </form>

        <div class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1" role="group" aria-label="Filter status">
            <Button size="sm" class="h-11 shrink-0" :variant="!filters.status ? 'default' : 'outline'" @click="filter()">Semua</Button>
            <Button
                v-for="status in statuses"
                :key="status.value"
                size="sm"
                class="h-11 shrink-0"
                :variant="filters.status === status.value ? 'default' : 'outline'"
                :aria-pressed="filters.status === status.value"
                @click="filter(status.value)"
            >
                {{ status.label }}
            </Button>
        </div>

        <EmptyState v-if="reports.data.length === 0" title="Belum ada laporan" description="Laporan yang Anda buat akan tampil di sini.">
            <Button as-child class="h-11"><Link :href="create()">Buat laporan pertama</Link></Button>
        </EmptyState>

        <ul v-else class="grid gap-3">
            <li v-for="report in reports.data" :key="report.id">
                <Link
                    :href="show.url(report.id)"
                    class="grid gap-2 rounded-xl border p-4 hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                >
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <span class="text-sm font-medium text-muted-foreground">{{ report.ticket_number }}</span>
                        <span class="flex items-center gap-2">
                            <span v-if="report.is_overdue" class="rounded-full bg-status-rejected-bg px-2.5 py-1 text-xs font-semibold text-status-rejected">Terlambat</span>
                            <StatusBadge :status="report.status" />
                        </span>
                    </div>
                    <p class="font-semibold">{{ report.title }}</p>
                    <p class="text-sm text-muted-foreground">
                        {{ report.category ?? '-' }} · {{ report.building ?? '-' }}, {{ report.room ?? '-' }} · {{ formatDate(report.created_at) }}
                    </p>
                </Link>
            </li>
        </ul>

        <Pagination :links="reports.links" />
    </div>
</template>
```

- [ ] **Step 3: `reports/Show.vue`**

```vue
<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Pencil } from '@lucide/vue';
import { ref } from 'vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import Timeline from '@/components/Timeline.vue';
import { Button } from '@/components/ui/button';
import { formatDate, formatDateTime, formatRupiah } from '@/lib/format';
import { cancel, edit, index } from '@/routes/reports';

type Photo = { id: number; url: string };

const props = defineProps<{
    report: {
        id: number;
        ticket_number: string;
        title: string;
        description: string;
        status: string;
        user_priority: string;
        admin_priority: string | null;
        is_overdue: boolean;
        sla_date: string | null;
        created_at: string;
        category: string | null;
        room: string | null;
        building: string | null;
        pic: string | null;
        asset: { asset_code: string; name: string } | null;
        rejection_reason: string | null;
        hold_reason: string | null;
        repair_action: string | null;
        completed_on: string | null;
        repair_cost: string | null;
        vendor_name: string | null;
        damage_photos: Photo[];
        completion_photos: Photo[];
        timeline: { id: number; to_status: string; to_label: string; actor: string | null; note: string | null; created_at: string }[];
    };
    can: { update: boolean; cancel: boolean };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Laporan Saya', href: index() }] },
});

const confirmCancel = ref(false);

function doCancel() {
    router.post(cancel.url(props.report.id), {}, { preserveScroll: true, onFinish: () => (confirmCancel.value = false) });
}

const details: [string, string][] = [
    ['Kategori', props.report.category ?? '-'],
    ['Lokasi', `${props.report.building ?? '-'}, ${props.report.room ?? '-'}`],
    ['Aset', props.report.asset ? `${props.report.asset.name} (${props.report.asset.asset_code})` : 'Tidak dipilih'],
    ['Urgensi Anda', props.report.user_priority],
    ['Prioritas admin', props.report.admin_priority ?? 'Belum ditentukan'],
    ['Target selesai (SLA)', props.report.sla_date ? formatDateTime(props.report.sla_date) : 'Belum ditentukan'],
    ['PIC', props.report.pic ?? 'Belum ditugaskan'],
    ['Dibuat', formatDateTime(props.report.created_at)],
];
</script>

<template>
    <Head :title="report.ticket_number" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4">
        <PageHeader :title="report.title" :description="report.ticket_number">
            <StatusBadge :status="report.status" />
            <span v-if="report.is_overdue" class="rounded-full bg-status-rejected-bg px-2.5 py-1 text-xs font-semibold text-status-rejected">Terlambat</span>
        </PageHeader>

        <div v-if="can.update || can.cancel" class="flex flex-wrap gap-2">
            <Button v-if="can.update" as-child variant="outline" class="h-11">
                <Link :href="edit.url(report.id)"><Pencil class="size-4" /> Ubah laporan</Link>
            </Button>
            <Button v-if="can.cancel" variant="outline" class="h-11" @click="confirmCancel = true">Batalkan laporan</Button>
        </div>

        <section v-if="report.rejection_reason" class="rounded-xl border border-destructive/40 bg-status-rejected-bg p-4 text-status-rejected" role="status">
            <h2 class="font-semibold">Laporan ditolak</h2>
            <p class="mt-1 text-sm">{{ report.rejection_reason }}</p>
        </section>

        <section v-if="report.hold_reason && report.status === 'On_Hold'" class="rounded-xl border bg-status-on-hold-bg p-4 text-status-on-hold" role="status">
            <h2 class="font-semibold">Pengerjaan ditunda</h2>
            <p class="mt-1 text-sm">{{ report.hold_reason }}</p>
        </section>

        <dl class="grid gap-3 rounded-xl border p-4 sm:grid-cols-2">
            <div v-for="[label, value] in details" :key="label">
                <dt class="text-sm text-muted-foreground">{{ label }}</dt>
                <dd class="font-medium">{{ value }}</dd>
            </div>
        </dl>

        <section aria-labelledby="desc">
            <h2 id="desc" class="mb-1 font-semibold">Deskripsi</h2>
            <p class="whitespace-pre-line">{{ report.description }}</p>
        </section>

        <section v-if="report.damage_photos.length" aria-labelledby="photos">
            <h2 id="photos" class="mb-2 font-semibold">Foto kerusakan</h2>
            <ul class="grid grid-cols-3 gap-2">
                <li v-for="photo in report.damage_photos" :key="photo.id">
                    <a :href="photo.url" target="_blank" rel="noopener" class="block aspect-square overflow-hidden rounded-lg border focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none">
                        <img :src="photo.url" alt="Foto kerusakan" class="size-full object-cover" loading="lazy" />
                    </a>
                </li>
            </ul>
        </section>

        <section v-if="report.status === 'Closed'" class="grid gap-3 rounded-xl border p-4" aria-labelledby="done">
            <h2 id="done" class="font-semibold">Hasil perbaikan</h2>
            <p class="whitespace-pre-line">{{ report.repair_action }}</p>
            <dl class="grid gap-3 sm:grid-cols-3">
                <div><dt class="text-sm text-muted-foreground">Tanggal selesai</dt><dd class="font-medium">{{ formatDate(report.completed_on) }}</dd></div>
                <div><dt class="text-sm text-muted-foreground">Biaya</dt><dd class="font-medium">{{ formatRupiah(report.repair_cost) }}</dd></div>
                <div><dt class="text-sm text-muted-foreground">Vendor</dt><dd class="font-medium">{{ report.vendor_name ?? '-' }}</dd></div>
            </dl>
            <ul v-if="report.completion_photos.length" class="grid grid-cols-3 gap-2">
                <li v-for="photo in report.completion_photos" :key="photo.id">
                    <a :href="photo.url" target="_blank" rel="noopener" class="block aspect-square overflow-hidden rounded-lg border focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none">
                        <img :src="photo.url" alt="Foto bukti perbaikan" class="size-full object-cover" loading="lazy" />
                    </a>
                </li>
            </ul>
        </section>

        <section aria-labelledby="timeline">
            <h2 id="timeline" class="mb-3 font-semibold">Riwayat</h2>
            <Timeline :items="report.timeline" />
        </section>
    </div>

    <ConfirmDialog
        v-model:open="confirmCancel"
        title="Batalkan laporan?"
        description="Laporan yang dibatalkan tidak bisa dibuka kembali."
        confirm-label="Ya, batalkan"
        destructive
        @confirm="doCancel"
    />
</template>
```

- [ ] **Step 4: Verify the whole plan**

Run: `php artisan test && composer lint:check && composer types:check && php artisan wayfinder:generate && npm run types:check && npm run check && npm run build`
Expected: all pass.

- [ ] **Step 5: Manual check (reporter journey at 360 px and 1280 px)**

Expected: filter chips scroll horizontally on mobile without page scroll; list shows cards with status text; detail shows timeline; editing a Pending report works; cancel asks for confirmation and the report becomes "Dibatalkan" with the edit/cancel buttons gone; Tab order reaches every control with a visible focus ring.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat: my reports list, detail page and status timeline"
```

---

## Self-Review (Plan 3)

- **Spec coverage:** F04 AC1 (pending + ticket + log) → Task 5 `SubmitReportTest`; AC2 (0001, 0002, monthly reset) → Task 2; AC3 (no photo) / AC4 (4 photos) → Task 5; AC5 (no asset) → Task 5; AC6 (room/category must exist) → Task 5 (the "room belongs to the chosen building" check is a UI dependency: the form only offers rooms of the selected building); AC7 (≤ 2 MB) → Task 7 manual check; AC8 (admins notified) → Task 5; AC9 (photo access 403) → Task 5 `AttachmentAccessTest`. F05 AC1–AC8 → Task 6 tests (`MyReportsTest`, `UpdateReportTest`, `CancelReportTest`, `ReportVisibilityTest`). F08 AC1–AC3 backend half (classes + recipients on submit) → Tasks 3 and 5; the other recipients are wired in Plan 4.
- **Placeholder scan:** none.
- **Type consistency:** `ReportTransitioner::{transition,note,logCreated}`, `AttachmentStore::{add,remove}`, `ReportPresenter::{summary,detail}`, `Timeline`, `PhotoUploader`, `StatusBadge` keys match what Plan 4 imports.
- **Review Focus:** all five lines have tests (Tasks 2, 5, 6).
- **Known note:** Task 7 `compressImage` returns the original file whenever re-encoding does not make it smaller, so files are never inflated; the server still enforces 2 MB.
