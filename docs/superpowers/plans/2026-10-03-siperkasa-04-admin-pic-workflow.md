# SiPerkasa Plan 4 — Admin Validation & PIC Workflow

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Admins review the incoming queue, then reject or validate a report (priority, SLA, PIC) and reassign PICs; PICs work their tasks (start, hold, resume) and close them with a completion form and photo proof. Every step notifies the right person and appears in the report timeline.

**Architecture:** Each use case is one Action class built on `ReportTransitioner` (Plan 3) and guarded by `ReportPolicy`. A reusable `ReportFilter` class drives the admin list (and the exports in Plan 5). Controllers stay thin: FormRequest → Gate → Action → redirect.

**Tech Stack:** Laravel 13, Pest 5, Inertia 3 + Vue 3 + TypeScript, MySQL 8.

**Spec:** `C:\Users\Lenovo\Documents\Obsidian Vault\40 - PROJECTS\SiPerkasa\04-Spec.md` (also `03-Tasks/F06-Validasi-Admin.md`, `F07-Pengerjaan-PIC.md`, `D:\siperkasa\.agents\rules\siperkasa.md`). Requires Plans 1–3.

## Global Constraints

- Admin actions: validate (`Pending→Assigned`), reject (`Pending→Rejected`), reassign (`Assigned|In_Progress|On_Hold`, status unchanged). PIC actions: start (`Assigned→In_Progress`), hold (`In_Progress→On_Hold`), resume (`On_Hold→In_Progress`), complete (`In_Progress→Closed`).
- Validate requires `admin_priority`, `sla_date` (in the future) and `assigned_pic_id` (an **active** user with role `pic`). Default SLA per priority: Critical 1, High 3, Medium 7, Low 14 days (`config/siperkasa.php`); admin may change it.
- Reject and Hold reasons: required, ≥ 10 characters. `started_at` is set once, on the first start.
- Completion form: `repair_action` required (≥ 5 chars); `completed_on` required, not in the future and not before the report's creation date; `repair_cost` optional, ≥ 0; `vendor_name` optional; completion photos 1–3 (same image rules as damage photos).
- PICs cannot reject a task; they use Hold. A report that is not in the status an action needs is refused with 403 (policy), never silently ignored.
- Notifications (database channel): validate → PIC (`ReportAssigned`) + reporter (`ReportStatusChanged`); reassign → new PIC + reporter; reject, start, hold, resume, complete → reporter.
- Admin list: default order = `Pending` oldest first, then everything else newest first; paginated 15; filters: `status, category_id, building_id, pic_id, priority (effective = admin priority, else user priority), from, to, overdue, q`.
- Reports are presented only through `ReportPresenter`; paginated props use `->through()`.
- UI: Bahasa Indonesia, tokens only, touch targets ≥ 44 px (`h-11`), cards on mobile and a table from `md`. Final/destructive actions (reject) go through `ConfirmDialog`.
- Commit messages must not contain any Claude attribution or `Co-Authored-By` trailer.

## Review Focus

- Two admins approving the same pending report: the second (stale) approval is refused with 403 and the PIC and SLA from the first approval are kept (Task 2).
- An SLA in the past, or equal to "now", is refused (Task 2).
- Assigning a report to a deactivated PIC is refused; moving a task away from a deactivated PIC works (Tasks 2 and 4).
- A repair cost of `0` and `1500000.50` are accepted; `-1` is refused (Task 7).
- A completion date before the report was created, or tomorrow, is refused (Task 7).

## File Structure

| File | Responsibility |
|---|---|
| `app/Support/ReportFilter.php` | Validated filter set + query application (also used by Plan 5) |
| `app/Actions/Reports/{ValidateReport,RejectReport,ReassignReport}.php` | Admin use cases |
| `app/Actions/Reports/{StartReport,HoldReport,ResumeReport,CompleteReport}.php` | PIC use cases |
| `app/Http/Requests/Admin/{ApproveReportRequest,RejectReportRequest,ReassignReportRequest}.php`, `app/Http/Requests/Pic/{HoldReportRequest,CompleteReportRequest}.php` | Validation |
| `app/Http/Controllers/Admin/ReportController.php`, `app/Http/Controllers/Pic/TaskController.php` | HTTP layer |
| `resources/js/pages/admin/reports/{Index,Show}.vue`, `resources/js/pages/pic/tasks/{Index,Show}.vue` | UI |

---

### Task 1: `ReportFilter` and the admin report list

**Files:**
- Create: `app/Support/ReportFilter.php`, `app/Http/Controllers/Admin/ReportController.php`, `tests/Feature/Admin/AdminReportListTest.php`
- Modify: `routes/web.php`

**Interfaces:**
- Produces:
  - `ReportFilter::rules(): array`, `ReportFilter::fromRequest(Request): self`, `->apply(Builder): Builder`, public `->values` (validated input).
  - Routes `admin.reports.index`, `admin.reports.show` (this task); `admin.reports.approve|reject|reassign` are added in Tasks 2–4.
  - Index props: `reports` (paginator of `ReportPresenter::summary`), `filters`, `options` `{categories, buildings, pics, statuses, priorities}`.

- [ ] **Step 1: Write the failing test** `tests/Feature/Admin/AdminReportListTest.php`

```php
<?php

use App\Enums\Priority;
use App\Models\Building;
use App\Models\Category;
use App\Models\Report;
use App\Models\Room;
use App\Models\User;

beforeEach(fn () => $this->admin = User::factory()->admin()->create());

test('admin sees every report with options for the filters', function () {
    Report::factory()->count(3)->create();

    $this->actingAs($this->admin)->get(route('admin.reports.index'))
        ->assertInertia(fn ($page) => $page
            ->component('admin/reports/Index')
            ->has('reports.data', 3)
            ->has('options.categories')
            ->has('options.buildings')
            ->has('options.pics')
            ->has('options.statuses', 7)
            ->has('options.priorities', 4));
});

test('pending reports come first, oldest first, then the rest newest first', function () {
    $closedOld = Report::factory()->closed()->create(['created_at' => now()->subDays(10)]);
    $closedNew = Report::factory()->closed()->create(['created_at' => now()->subDay()]);
    $pendingNew = Report::factory()->create(['created_at' => now()->subHour()]);
    $pendingOld = Report::factory()->create(['created_at' => now()->subDays(3)]);

    $this->actingAs($this->admin)->get(route('admin.reports.index'))
        ->assertInertia(fn ($page) => $page
            ->where('reports.data.0.id', $pendingOld->id)
            ->where('reports.data.1.id', $pendingNew->id)
            ->where('reports.data.2.id', $closedNew->id)
            ->where('reports.data.3.id', $closedOld->id));
});

test('the list is paginated at 15 per page', function () {
    Report::factory()->count(17)->create();

    $this->actingAs($this->admin)->get(route('admin.reports.index'))
        ->assertInertia(fn ($page) => $page->has('reports.data', 15)->has('reports.links'));
});

test('filters narrow the list', function () {
    $category = Category::factory()->create();
    $building = Building::factory()->create();
    $room = Room::factory()->for($building)->create();
    $pic = User::factory()->pic()->create();

    $target = Report::factory()->assigned($pic)->create([
        'category_id' => $category->id,
        'room_id' => $room->id,
        'user_priority' => Priority::Low,
        'admin_priority' => Priority::Critical,
        'title' => 'AC ruang rapat bocor',
        'created_at' => '2026-10-10 10:00:00',
        'sla_date' => now()->subDay(),
    ]);
    Report::factory()->count(2)->create(['created_at' => '2026-09-01 10:00:00', 'user_priority' => Priority::Medium]);

    $expectCount = function (array $query, int $expected) {
        $this->actingAs($this->admin)->get(route('admin.reports.index', $query))
            ->assertInertia(fn ($page) => $page->has('reports.data', $expected));
    };

    $expectCount(['status' => 'Assigned'], 1);
    $expectCount(['status' => 'Closed'], 0);
    $expectCount(['category_id' => $category->id], 1);
    $expectCount(['building_id' => $building->id], 1);
    $expectCount(['pic_id' => $pic->id], 1);
    $expectCount(['priority' => 'Critical'], 1);   // admin priority wins over the reporter's "Low"
    $expectCount(['priority' => 'Low'], 0);
    $expectCount(['from' => '2026-10-01', 'to' => '2026-10-31'], 1);
    $expectCount(['from' => '2026-09-01', 'to' => '2026-09-30'], 2);
    $expectCount(['overdue' => 1], 1);
    $expectCount(['q' => 'rapat'], 1);
    $expectCount(['q' => $target->ticket_number], 1);
});

test('a pending report is filtered by the reporter priority', function () {
    Report::factory()->create(['user_priority' => Priority::High]);
    Report::factory()->create(['user_priority' => Priority::Low]);

    $this->actingAs($this->admin)->get(route('admin.reports.index', ['priority' => 'High']))
        ->assertInertia(fn ($page) => $page->has('reports.data', 1));
});

test('invalid filters are validation errors', function () {
    $this->actingAs($this->admin)->get(route('admin.reports.index', ['status' => 'Nope', 'to' => '2026-01-01', 'from' => '2026-02-01']))
        ->assertSessionHasErrors(['status', 'to']);
});

test('non-admins are forbidden', function (string $state) {
    $this->actingAs(User::factory()->{$state}()->create())->get(route('admin.reports.index'))->assertForbidden();
})->with(['reporter', 'pic']);

test('the detail page shows the full report with the allowed actions', function () {
    $report = Report::factory()->create();

    $this->actingAs($this->admin)->get(route('admin.reports.show', $report))
        ->assertInertia(fn ($page) => $page
            ->component('admin/reports/Show')
            ->where('report.id', $report->id)
            ->where('can.approve', true)
            ->where('can.reject', true)
            ->where('can.reassign', false));
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `php artisan test tests/Feature/Admin/AdminReportListTest.php`
Expected: FAIL (`Route [admin.reports.index] not defined`).

- [ ] **Step 3: Create `app/Support/ReportFilter.php`**

```php
<?php

namespace App\Support;

use App\Enums\Priority;
use App\Enums\ReportStatus;
use App\Models\Report;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** The admin's report filters; shared by the list, dashboard drill-downs and exports. */
final class ReportFilter
{
    /** @param array<string, mixed> $values */
    public function __construct(public readonly array $values) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(ReportStatus::class)],
            'category_id' => ['nullable', 'integer'],
            'building_id' => ['nullable', 'integer'],
            'pic_id' => ['nullable', 'integer'],
            'priority' => ['nullable', Rule::enum(Priority::class)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'overdue' => ['nullable', 'boolean'],
            'q' => ['nullable', 'string', 'max:100'],
        ];
    }

    public static function fromRequest(Request $request): self
    {
        return new self($request->validate(self::rules()));
    }

    /**
     * @param  Builder<Report>  $query
     * @return Builder<Report>
     */
    public function apply(Builder $query): Builder
    {
        $v = $this->values;

        return $query
            ->when($v['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($v['category_id'] ?? null, fn ($q, $id) => $q->where('category_id', $id))
            ->when($v['building_id'] ?? null, fn ($q, $id) => $q->whereHas('room', fn ($room) => $room->where('building_id', $id)))
            ->when($v['pic_id'] ?? null, fn ($q, $id) => $q->where('assigned_pic_id', $id))
            ->when($v['priority'] ?? null, fn ($q, $priority) => $q->whereRaw('COALESCE(admin_priority, user_priority) = ?', [$priority]))
            ->when($v['from'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($v['to'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
            ->when($v['overdue'] ?? false, fn ($q) => $q->overdue())
            ->when($v['q'] ?? null, function ($q, string $term) {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $q->where(fn ($inner) => $inner->where('ticket_number', 'like', $like)->orWhere('title', 'like', $like));
            });
    }
}
```

- [ ] **Step 4: Create `app/Http/Controllers/Admin/ReportController.php`** (list and detail; later tasks add the actions)

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Priority;
use App\Enums\ReportStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Models\Category;
use App\Models\Report;
use App\Models\User;
use App\Support\ReportFilter;
use App\Support\ReportPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Report::class);

        $filter = ReportFilter::fromRequest($request);

        $reports = $filter->apply(Report::query())
            ->with(['category:id,name', 'room.building:id,name', 'user:id,name', 'assignedPic:id,name'])
            ->orderByRaw("CASE WHEN status = 'Pending' THEN 0 ELSE 1 END")
            ->orderByRaw("CASE WHEN status = 'Pending' THEN created_at END ASC")
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Report $report) => ReportPresenter::summary($report));

        return Inertia::render('admin/reports/Index', [
            'reports' => $reports,
            'filters' => $filter->values,
            'options' => [
                'categories' => Category::orderBy('name')->get(['id', 'name']),
                'buildings' => Building::orderBy('code')->get(['id', 'name']),
                'pics' => User::where('role', UserRole::Pic)->orderBy('name')->get(['id', 'name']),
                'statuses' => collect(ReportStatus::cases())->map(fn (ReportStatus $s) => ['value' => $s->value, 'label' => $s->label()]),
                'priorities' => collect(Priority::cases())->map(fn (Priority $p) => ['value' => $p->value, 'label' => $p->label()]),
            ],
        ]);
    }

    public function show(Report $report): Response
    {
        Gate::authorize('view', $report);

        $report->loadMissing('category');

        $activePics = User::query()
            ->where('role', UserRole::Pic)
            ->where('is_active', true)
            ->withCount(['assignedReports as active_tasks' => fn ($query) => $query->whereNotIn('status', ReportStatus::finalValues())])
            ->orderBy('name')
            ->get(['id', 'name']);

        $suggested = $report->category?->default_pic_id;

        return Inertia::render('admin/reports/Show', [
            'report' => ReportPresenter::detail($report),
            'can' => [
                'approve' => Gate::allows('validate', $report),
                'reject' => Gate::allows('reject', $report),
                'reassign' => Gate::allows('reassign', $report),
            ],
            'pics' => $activePics->map(fn (User $pic) => ['id' => $pic->id, 'name' => $pic->name, 'active_tasks' => $pic->active_tasks]),
            'current_pic_id' => $report->assigned_pic_id,
            'suggested_pic_id' => $activePics->contains('id', $suggested) ? $suggested : null,
            'priorities' => collect(Priority::cases())->map(fn (Priority $p) => ['value' => $p->value, 'label' => $p->label()]),
            'sla_defaults' => collect(Priority::cases())->mapWithKeys(
                fn (Priority $p) => [$p->value => now()->addDays($p->slaDays())->format('Y-m-d\TH:i')],
            ),
        ]);
    }
}
```

- [ ] **Step 5: Routes.** In `routes/web.php` add `use App\Http\Controllers\Admin\ReportController as AdminReportController;` and inside the `role:admin` group:

```php
        Route::get('reports', [AdminReportController::class, 'index'])->name('reports.index');
        Route::get('reports/{report}', [AdminReportController::class, 'show'])->name('reports.show');
```

Create stub pages `resources/js/pages/admin/reports/{Index,Show}.vue` (`<template><div /></template>`) so Inertia's page-exists check passes; Task 5 replaces them.

- [ ] **Step 6: Run the tests**

Run: `php artisan test tests/Feature/Admin/AdminReportListTest.php`
Expected: all pass.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat: admin report list with filters and detail endpoint"
```

---

### Task 2: Validate a report (priority, SLA, PIC)

**Files:**
- Create: `app/Actions/Reports/ValidateReport.php`, `app/Http/Requests/Admin/ApproveReportRequest.php`, `tests/Feature/Admin/ValidateReportTest.php`
- Modify: `app/Http/Controllers/Admin/ReportController.php`, `routes/web.php`

**Interfaces:**
- Produces: `ValidateReport::handle(Report $report, User $admin, Priority $priority, CarbonInterface $sla, User $pic): Report`; route `POST admin/reports/{report}/approve` (`admin.reports.approve`) with fields `admin_priority`, `sla_date` (`Y-m-d\TH:i`), `assigned_pic_id`.

- [ ] **Step 1: Write the failing test** `tests/Feature/Admin/ValidateReportTest.php`

```php
<?php

use App\Enums\Priority;
use App\Enums\ReportStatus;
use App\Models\Category;
use App\Models\Report;
use App\Models\User;
use App\Notifications\ReportAssigned;
use App\Notifications\ReportStatusChanged;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->admin = User::factory()->admin()->create();
    $this->pic = User::factory()->pic()->create();
    $this->report = Report::factory()->create(['user_priority' => Priority::Medium]);
});

function approvePayload(User $pic, array $overrides = []): array
{
    return array_merge([
        'admin_priority' => 'High',
        'sla_date' => now()->addDays(3)->format('Y-m-d\TH:i'),
        'assigned_pic_id' => $pic->id,
    ], $overrides);
}

test('the detail page proposes the SLA per priority and the category default pic', function () {
    $this->travelTo(Carbon::parse('2026-10-15 10:00', 'Asia/Jakarta'));
    $category = Category::factory()->create(['default_pic_id' => $this->pic->id]);
    $report = Report::factory()->create(['category_id' => $category->id]);

    $this->actingAs($this->admin)->get(route('admin.reports.show', $report))
        ->assertInertia(fn ($page) => $page
            ->where('suggested_pic_id', $this->pic->id)
            ->where('sla_defaults.Critical', '2026-10-16T10:00')
            ->where('sla_defaults.High', '2026-10-18T10:00')
            ->where('sla_defaults.Medium', '2026-10-22T10:00')
            ->where('sla_defaults.Low', '2026-10-29T10:00')
            ->has('pics', 1));
});

test('an inactive default pic is not suggested and not offered', function () {
    $inactive = User::factory()->pic()->inactive()->create();
    $category = Category::factory()->create(['default_pic_id' => $inactive->id]);
    $report = Report::factory()->create(['category_id' => $category->id]);

    $this->actingAs($this->admin)->get(route('admin.reports.show', $report))
        ->assertInertia(fn ($page) => $page->where('suggested_pic_id', null)->has('pics', 1));
});

test('validating assigns the report and records everything', function () {
    $this->travelTo(Carbon::parse('2026-10-15 10:00', 'Asia/Jakarta'));

    $this->actingAs($this->admin)->post(route('admin.reports.approve', $this->report), approvePayload($this->pic))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.reports.show', $this->report));

    $report = $this->report->fresh();
    expect($report->status)->toBe(ReportStatus::Assigned)
        ->and($report->admin_id)->toBe($this->admin->id)
        ->and($report->admin_priority)->toBe(Priority::High)
        ->and($report->assigned_pic_id)->toBe($this->pic->id)
        ->and($report->sla_date->format('Y-m-d H:i'))->toBe('2026-10-18 10:00')
        ->and($report->validated_at)->not->toBeNull();

    $log = $report->statusLogs()->sole();
    expect($log->from_status)->toBe('Pending')->and($log->to_status)->toBe('Assigned')->and($log->actor_id)->toBe($this->admin->id);

    Notification::assertSentTo($this->pic, ReportAssigned::class);
    Notification::assertSentTo($report->user, ReportStatusChanged::class, fn ($n) => $n->report->status === ReportStatus::Assigned);
});

test('required fields and rules are validated', function () {
    $this->actingAs($this->admin)->post(route('admin.reports.approve', $this->report), [])
        ->assertSessionHasErrors(['admin_priority', 'sla_date', 'assigned_pic_id']);

    expect($this->report->fresh()->status)->toBe(ReportStatus::Pending);
});

test('the SLA must be in the future', function () {
    $this->actingAs($this->admin)->post(route('admin.reports.approve', $this->report), approvePayload($this->pic, ['sla_date' => now()->subDay()->format('Y-m-d\TH:i')]))
        ->assertSessionHasErrors('sla_date');

    $this->actingAs($this->admin)->post(route('admin.reports.approve', $this->report), approvePayload($this->pic, ['sla_date' => now()->format('Y-m-d\TH:i')]))
        ->assertSessionHasErrors('sla_date');

    expect($this->report->fresh()->status)->toBe(ReportStatus::Pending);
});

test('the assignee must be an active pic', function (string $state, bool $active) {
    $user = User::factory()->{$state}()->create(['is_active' => $active]);

    $this->actingAs($this->admin)->post(route('admin.reports.approve', $this->report), approvePayload($user))
        ->assertSessionHasErrors('assigned_pic_id');

    expect($this->report->fresh()->status)->toBe(ReportStatus::Pending);
})->with([['reporter', true], ['admin', true], ['pic', false]]);

test('a second, stale approval is refused and keeps the first assignment', function () {
    $otherPic = User::factory()->pic()->create();

    $this->actingAs($this->admin)->post(route('admin.reports.approve', $this->report), approvePayload($this->pic))->assertRedirect();
    $this->actingAs(User::factory()->admin()->create())->post(route('admin.reports.approve', $this->report), approvePayload($otherPic, ['admin_priority' => 'Low']))->assertForbidden();

    $report = $this->report->fresh();
    expect($report->assigned_pic_id)->toBe($this->pic->id)
        ->and($report->admin_priority)->toBe(Priority::High)
        ->and($report->statusLogs()->count())->toBe(1);
});

test('only admins can validate', function (string $state) {
    $this->actingAs(User::factory()->{$state}()->create())->post(route('admin.reports.approve', $this->report), approvePayload($this->pic))->assertForbidden();
})->with(['reporter', 'pic']);
```

- [ ] **Step 2: Run to verify it fails**

Run: `php artisan test tests/Feature/Admin/ValidateReportTest.php`
Expected: FAIL (`Route [admin.reports.approve] not defined`).

- [ ] **Step 3: Create the action** `app/Actions/Reports/ValidateReport.php`

```php
<?php

namespace App\Actions\Reports;

use App\Enums\Priority;
use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\User;
use App\Notifications\ReportAssigned;
use App\Notifications\ReportStatusChanged;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

final class ValidateReport
{
    public function __construct(private readonly ReportTransitioner $transitioner) {}

    public function handle(Report $report, User $admin, Priority $priority, CarbonInterface $sla, User $pic): Report
    {
        $report = DB::transaction(fn () => $this->transitioner->transition(
            $report,
            ReportStatus::Assigned,
            $admin,
            "Ditugaskan ke {$pic->name}",
            [
                'admin_id' => $admin->id,
                'admin_priority' => $priority,
                'sla_date' => $sla,
                'assigned_pic_id' => $pic->id,
                'validated_at' => now(),
            ],
        ));

        Notification::send($pic, new ReportAssigned($report));
        Notification::send($report->user, new ReportStatusChanged($report));

        return $report;
    }
}
```

- [ ] **Step 4: Create the request** `app/Http/Requests/Admin/ApproveReportRequest.php`

```php
<?php

namespace App\Http\Requests\Admin;

use App\Enums\Priority;
use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApproveReportRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'admin_priority' => ['required', Rule::enum(Priority::class)],
            'sla_date' => ['required', 'date', 'after:now'],
            'assigned_pic_id' => [
                'required',
                Rule::exists('users', 'id')->where('role', UserRole::Pic->value)->where('is_active', true),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'sla_date.after' => 'SLA harus di masa depan.',
            'assigned_pic_id.exists' => 'PIC harus pengguna aktif dengan peran PIC.',
        ];
    }
}
```

- [ ] **Step 5: Controller method.** In `Admin\ReportController` add `use` lines for `App\Actions\Reports\ValidateReport`, `App\Http\Requests\Admin\ApproveReportRequest`, `Illuminate\Http\RedirectResponse`, `Illuminate\Support\Facades\Date`, and add:

```php
    public function approve(ApproveReportRequest $request, Report $report, ValidateReport $validate): RedirectResponse
    {
        Gate::authorize('validate', $report);

        $validate->handle(
            $report,
            $request->user(),
            Priority::from($request->validated('admin_priority')),
            Date::parse($request->validated('sla_date')),
            User::findOrFail($request->validated('assigned_pic_id')),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => "Laporan {$report->ticket_number} divalidasi dan ditugaskan."]);

        return to_route('admin.reports.show', $report);
    }
```

Route (inside `role:admin`): `Route::post('reports/{report}/approve', [AdminReportController::class, 'approve'])->name('reports.approve');`

- [ ] **Step 6: Run the tests**

Run: `php artisan test tests/Feature/Admin/ValidateReportTest.php`
Expected: all pass.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat: admin validates a report with priority, SLA and PIC"
```

---

### Task 3: Reject a report

**Files:**
- Create: `app/Actions/Reports/RejectReport.php`, `app/Http/Requests/Admin/RejectReportRequest.php`, `tests/Feature/Admin/RejectReportTest.php`
- Modify: `app/Http/Controllers/Admin/ReportController.php`, `routes/web.php`

**Interfaces:**
- Produces: `RejectReport::handle(Report $report, User $admin, string $reason): Report`; route `POST admin/reports/{report}/reject` (`admin.reports.reject`) with `rejection_reason`.

- [ ] **Step 1: Write the failing test** `tests/Feature/Admin/RejectReportTest.php`

```php
<?php

use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\User;
use App\Notifications\ReportStatusChanged;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->admin = User::factory()->admin()->create();
    $this->report = Report::factory()->create();
});

test('rejecting stores the reason, closes the report and tells the reporter', function () {
    $reason = 'Laporan ini duplikat dengan tiket yang sudah ada.';

    $this->actingAs($this->admin)->post(route('admin.reports.reject', $this->report), ['rejection_reason' => $reason])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.reports.show', $this->report));

    $report = $this->report->fresh();
    expect($report->status)->toBe(ReportStatus::Rejected)
        ->and($report->rejection_reason)->toBe($reason)
        ->and($report->admin_id)->toBe($this->admin->id);

    $log = $report->statusLogs()->sole();
    expect($log->to_status)->toBe('Rejected')->and($log->note)->toBe($reason);

    Notification::assertSentTo($report->user, ReportStatusChanged::class, fn ($n) => str_contains($n->toArray($report->user)['message'], $reason));
});

test('a reason of at least 10 characters is required', function () {
    $this->actingAs($this->admin)->post(route('admin.reports.reject', $this->report), [])
        ->assertSessionHasErrors('rejection_reason');

    $this->actingAs($this->admin)->post(route('admin.reports.reject', $this->report), ['rejection_reason' => 'pendek'])
        ->assertSessionHasErrors(['rejection_reason' => 'Alasan penolakan minimal 10 karakter.']);

    expect($this->report->fresh()->status)->toBe(ReportStatus::Pending);
});

test('a rejected report can not be rejected or validated again', function () {
    $this->actingAs($this->admin)->post(route('admin.reports.reject', $this->report), ['rejection_reason' => 'Alasan yang cukup panjang.']);

    $this->actingAs($this->admin)->post(route('admin.reports.reject', $this->report), ['rejection_reason' => 'Alasan kedua yang panjang.'])->assertForbidden();
    $this->actingAs($this->admin)->post(route('admin.reports.approve', $this->report), [
        'admin_priority' => 'High',
        'sla_date' => now()->addDay()->format('Y-m-d\TH:i'),
        'assigned_pic_id' => User::factory()->pic()->create()->id,
    ])->assertForbidden();

    expect($this->report->fresh()->rejection_reason)->toBe('Alasan yang cukup panjang.');
});

test('only admins can reject', function (string $state) {
    $this->actingAs(User::factory()->{$state}()->create())
        ->post(route('admin.reports.reject', $this->report), ['rejection_reason' => 'Alasan yang cukup panjang.'])->assertForbidden();
})->with(['reporter', 'pic']);
```

- [ ] **Step 2: Run to verify it fails**

Run: `php artisan test tests/Feature/Admin/RejectReportTest.php`
Expected: FAIL (`Route [admin.reports.reject] not defined`).

- [ ] **Step 3: Action** `app/Actions/Reports/RejectReport.php`

```php
<?php

namespace App\Actions\Reports;

use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\User;
use App\Notifications\ReportStatusChanged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

final class RejectReport
{
    public function __construct(private readonly ReportTransitioner $transitioner) {}

    public function handle(Report $report, User $admin, string $reason): Report
    {
        $report = DB::transaction(fn () => $this->transitioner->transition(
            $report,
            ReportStatus::Rejected,
            $admin,
            $reason,
            ['admin_id' => $admin->id, 'rejection_reason' => $reason],
        ));

        Notification::send($report->user, new ReportStatusChanged($report, "Laporan {$report->ticket_number} ditolak: {$reason}"));

        return $report;
    }
}
```

- [ ] **Step 4: Request** `app/Http/Requests/Admin/RejectReportRequest.php`

```php
<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class RejectReportRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'rejection_reason' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'rejection_reason.required' => 'Alasan penolakan wajib diisi.',
            'rejection_reason.min' => 'Alasan penolakan minimal 10 karakter.',
        ];
    }
}
```

- [ ] **Step 5: Controller method and route.** In `Admin\ReportController` add `use App\Actions\Reports\RejectReport; use App\Http\Requests\Admin\RejectReportRequest;` and:

```php
    public function reject(RejectReportRequest $request, Report $report, RejectReport $reject): RedirectResponse
    {
        Gate::authorize('reject', $report);

        $reject->handle($report, $request->user(), $request->validated('rejection_reason'));

        Inertia::flash('toast', ['type' => 'success', 'message' => "Laporan {$report->ticket_number} ditolak."]);

        return to_route('admin.reports.show', $report);
    }
```

Route: `Route::post('reports/{report}/reject', [AdminReportController::class, 'reject'])->name('reports.reject');`

- [ ] **Step 6: Run the tests**

Run: `php artisan test tests/Feature/Admin`
Expected: all pass.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat: admin rejects a report with a mandatory reason"
```

---

### Task 4: Reassign the PIC

**Files:**
- Create: `app/Actions/Reports/ReassignReport.php`, `app/Http/Requests/Admin/ReassignReportRequest.php`, `tests/Feature/Admin/ReassignReportTest.php`
- Modify: `app/Http/Controllers/Admin/ReportController.php`, `routes/web.php`

**Interfaces:**
- Produces: `ReassignReport::handle(Report $report, User $admin, User $newPic): Report`; route `POST admin/reports/{report}/reassign` (`admin.reports.reassign`) with `assigned_pic_id`.

- [ ] **Step 1: Write the failing test** `tests/Feature/Admin/ReassignReportTest.php`

```php
<?php

use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\User;
use App\Notifications\ReportAssigned;
use App\Notifications\ReportStatusChanged;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->admin = User::factory()->admin()->create();
    $this->oldPic = User::factory()->pic()->create(['name' => 'Pak Lama']);
    $this->newPic = User::factory()->pic()->create(['name' => 'Bu Baru']);
});

test('reassigning keeps the status, logs the change and notifies the new pic and the reporter', function (string $state, ReportStatus $status) {
    $report = Report::factory()->{$state}($this->oldPic)->create();

    $this->actingAs($this->admin)->post(route('admin.reports.reassign', $report), ['assigned_pic_id' => $this->newPic->id])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.reports.show', $report));

    $report->refresh();
    expect($report->assigned_pic_id)->toBe($this->newPic->id)
        ->and($report->status)->toBe($status);

    $log = $report->statusLogs()->get()->last();
    expect($log->from_status)->toBe($status->value)
        ->and($log->to_status)->toBe($status->value)
        ->and($log->note)->toBe('PIC: Pak Lama → Bu Baru');

    Notification::assertSentTo($this->newPic, ReportAssigned::class);
    Notification::assertSentTo($report->user, ReportStatusChanged::class);
})->with([
    ['assigned', ReportStatus::Assigned],
    ['inProgress', ReportStatus::InProgress],
    ['onHold', ReportStatus::OnHold],
]);

test('the new pic must differ from the current one and be active', function () {
    $report = Report::factory()->inProgress($this->oldPic)->create();
    $inactive = User::factory()->pic()->inactive()->create();

    $this->actingAs($this->admin)->post(route('admin.reports.reassign', $report), ['assigned_pic_id' => $this->oldPic->id])
        ->assertSessionHasErrors(['assigned_pic_id' => 'PIC baru harus berbeda dari PIC saat ini.']);

    $this->actingAs($this->admin)->post(route('admin.reports.reassign', $report), ['assigned_pic_id' => $inactive->id])
        ->assertSessionHasErrors('assigned_pic_id');

    expect($report->fresh()->assigned_pic_id)->toBe($this->oldPic->id);
});

test('a task can be moved away from a pic who has since been deactivated', function () {
    $report = Report::factory()->inProgress($this->oldPic)->create();
    $this->oldPic->update(['is_active' => false]);

    $this->actingAs($this->admin)->post(route('admin.reports.reassign', $report), ['assigned_pic_id' => $this->newPic->id])
        ->assertSessionHasNoErrors();

    expect($report->fresh()->assigned_pic_id)->toBe($this->newPic->id);
});

test('finished or unassigned reports can not be reassigned', function (string $state) {
    $report = Report::factory()->{$state}()->create();

    $this->actingAs($this->admin)->post(route('admin.reports.reassign', $report), ['assigned_pic_id' => $this->newPic->id])->assertForbidden();
})->with(['pending', 'closed', 'rejected', 'cancelled']);

test('only admins can reassign', function (string $state) {
    $report = Report::factory()->assigned($this->oldPic)->create();

    $this->actingAs(User::factory()->{$state}()->create())->post(route('admin.reports.reassign', $report), ['assigned_pic_id' => $this->newPic->id])->assertForbidden();
})->with(['reporter', 'pic']);
```

(The check that the previous PIC loses access to the task is added in Task 6's `TaskListTest`, because it needs the PIC routes.)

- [ ] **Step 2: Run to verify it fails**

Run: `php artisan test tests/Feature/Admin/ReassignReportTest.php`
Expected: FAIL (`Route [admin.reports.reassign] not defined`).

- [ ] **Step 3: Action** `app/Actions/Reports/ReassignReport.php`

```php
<?php

namespace App\Actions\Reports;

use App\Models\Report;
use App\Models\User;
use App\Notifications\ReportAssigned;
use App\Notifications\ReportStatusChanged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

final class ReassignReport
{
    public function __construct(private readonly ReportTransitioner $transitioner) {}

    public function handle(Report $report, User $admin, User $newPic): Report
    {
        $oldName = $report->assignedPic?->name ?? '-';

        DB::transaction(function () use ($report, $admin, $newPic, $oldName) {
            $report->update(['assigned_pic_id' => $newPic->id]);
            $this->transitioner->note($report, $admin, "PIC: {$oldName} → {$newPic->name}");
        });

        Notification::send($newPic, new ReportAssigned($report));
        Notification::send($report->user, new ReportStatusChanged($report, "Laporan {$report->ticket_number} dialihkan ke PIC {$newPic->name}."));

        return $report;
    }
}
```

- [ ] **Step 4: Request** `app/Http/Requests/Admin/ReassignReportRequest.php`

```php
<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReassignReportRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'assigned_pic_id' => [
                'required',
                Rule::exists('users', 'id')->where('role', UserRole::Pic->value)->where('is_active', true),
                Rule::notIn([$this->route('report')->assigned_pic_id]),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'assigned_pic_id.exists' => 'PIC harus pengguna aktif dengan peran PIC.',
            'assigned_pic_id.not_in' => 'PIC baru harus berbeda dari PIC saat ini.',
        ];
    }
}
```

Add `'not_in' => ':Attribute yang dipilih tidak valid.'` to `lang/id/validation.php` if it is not there yet.

- [ ] **Step 5: Controller method and route.** In `Admin\ReportController` add `use App\Actions\Reports\ReassignReport; use App\Http\Requests\Admin\ReassignReportRequest;` and:

```php
    public function reassign(ReassignReportRequest $request, Report $report, ReassignReport $reassign): RedirectResponse
    {
        Gate::authorize('reassign', $report);

        $reassign->handle($report, $request->user(), User::findOrFail($request->validated('assigned_pic_id')));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'PIC dipindahkan.']);

        return to_route('admin.reports.show', $report);
    }
```

Route: `Route::post('reports/{report}/reassign', [AdminReportController::class, 'reassign'])->name('reports.reassign');`

- [ ] **Step 6: Run the tests**

Run: `php artisan test tests/Feature/Admin`
Expected: all pass.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat: admin reassigns a report to another PIC"
```

---

### Task 5: Admin pages (queue and detail)

**Files:**
- Replace stubs: `resources/js/pages/admin/reports/{Index,Show}.vue`
- Modify: `resources/js/composables/useRoleNav.ts`

**Interfaces:**
- Consumes the props defined in Tasks 1–4. Uses `StatusBadge`, `Timeline`, `ConfirmDialog` (prop `error`, event `confirm(reason)`), `Pagination`, `EmptyState`, `PageHeader`, `NativeSelect`.

- [ ] **Step 1: `admin/reports/Index.vue`**

```vue
<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { formatDate, formatDateTime } from '@/lib/format';
import { index, show } from '@/routes/admin/reports';

type Row = {
    id: number;
    ticket_number: string;
    title: string;
    status: string;
    user_priority: string;
    admin_priority: string | null;
    is_overdue: boolean;
    sla_date: string | null;
    created_at: string;
    category: string | null;
    room: string | null;
    building: string | null;
    reporter: string | null;
    pic: string | null;
};

type Option = { id: number; name: string };
type Choice = { value: string; label: string };

const props = defineProps<{
    reports: { data: Row[]; links: { url: string | null; label: string; active: boolean }[] };
    filters: Record<string, string | number | boolean | undefined>;
    options: { categories: Option[]; buildings: Option[]; pics: Option[]; statuses: Choice[]; priorities: Choice[] };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Laporan', href: index() }] },
});

const f = reactive({
    q: String(props.filters.q ?? ''),
    status: String(props.filters.status ?? ''),
    category_id: String(props.filters.category_id ?? ''),
    building_id: String(props.filters.building_id ?? ''),
    pic_id: String(props.filters.pic_id ?? ''),
    priority: String(props.filters.priority ?? ''),
    from: String(props.filters.from ?? ''),
    to: String(props.filters.to ?? ''),
    overdue: props.filters.overdue === true || props.filters.overdue === '1' || props.filters.overdue === 1,
});

function apply() {
    const query = Object.fromEntries(
        Object.entries(f).filter(([, value]) => value !== '' && value !== false).map(([key, value]) => [key, value === true ? 1 : value]),
    );
    router.get(index.url(), query, { preserveState: true, replace: true });
}

function reset() {
    Object.assign(f, { q: '', status: '', category_id: '', building_id: '', pic_id: '', priority: '', from: '', to: '', overdue: false });
    apply();
}
</script>

<template>
    <Head title="Laporan" />

    <div class="flex flex-col gap-4 p-4">
        <PageHeader title="Laporan Kerusakan" description="Validasi laporan baru, pantau SLA, dan pindahkan PIC bila perlu." />

        <form class="grid gap-3 rounded-xl border p-4 sm:grid-cols-2 lg:grid-cols-4" @submit.prevent="apply">
            <div class="grid gap-1 sm:col-span-2">
                <Label for="f-q">Cari</Label>
                <Input id="f-q" v-model="f.q" class="h-11" placeholder="Nomor tiket atau judul" />
            </div>
            <div class="grid gap-1">
                <Label for="f-status">Status</Label>
                <NativeSelect id="f-status" v-model="f.status">
                    <option value="">Semua status</option>
                    <option v-for="s in options.statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                </NativeSelect>
            </div>
            <div class="grid gap-1">
                <Label for="f-priority">Prioritas</Label>
                <NativeSelect id="f-priority" v-model="f.priority">
                    <option value="">Semua prioritas</option>
                    <option v-for="p in options.priorities" :key="p.value" :value="p.value">{{ p.label }}</option>
                </NativeSelect>
            </div>
            <div class="grid gap-1">
                <Label for="f-category">Kategori</Label>
                <NativeSelect id="f-category" v-model="f.category_id">
                    <option value="">Semua kategori</option>
                    <option v-for="c in options.categories" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
                </NativeSelect>
            </div>
            <div class="grid gap-1">
                <Label for="f-building">Gedung</Label>
                <NativeSelect id="f-building" v-model="f.building_id">
                    <option value="">Semua gedung</option>
                    <option v-for="b in options.buildings" :key="b.id" :value="String(b.id)">{{ b.name }}</option>
                </NativeSelect>
            </div>
            <div class="grid gap-1">
                <Label for="f-pic">PIC</Label>
                <NativeSelect id="f-pic" v-model="f.pic_id">
                    <option value="">Semua PIC</option>
                    <option v-for="p in options.pics" :key="p.id" :value="String(p.id)">{{ p.name }}</option>
                </NativeSelect>
            </div>
            <div class="grid gap-1">
                <Label for="f-from">Dari tanggal</Label>
                <Input id="f-from" v-model="f.from" type="date" class="h-11" />
            </div>
            <div class="grid gap-1">
                <Label for="f-to">Sampai tanggal</Label>
                <Input id="f-to" v-model="f.to" type="date" class="h-11" />
            </div>
            <Label for="f-overdue" class="flex min-h-11 items-center gap-3">
                <input id="f-overdue" v-model="f.overdue" type="checkbox" class="size-5" />
                <span>Hanya yang terlambat</span>
            </Label>
            <div class="flex flex-wrap gap-2 sm:col-span-2 lg:col-span-4">
                <Button type="submit" class="h-11">Terapkan filter</Button>
                <Button type="button" variant="outline" class="h-11" @click="reset">Atur ulang</Button>
            </div>
        </form>

        <EmptyState v-if="reports.data.length === 0" title="Tidak ada laporan" description="Ubah filter untuk melihat laporan lain." />

        <template v-else>
            <ul class="grid gap-3 md:hidden">
                <li v-for="r in reports.data" :key="r.id">
                    <Link :href="show.url(r.id)" class="grid gap-2 rounded-xl border p-4 hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="text-sm font-medium text-muted-foreground">{{ r.ticket_number }}</span>
                            <span class="flex items-center gap-2">
                                <span v-if="r.is_overdue" class="rounded-full bg-status-rejected-bg px-2.5 py-1 text-xs font-semibold text-status-rejected">Terlambat</span>
                                <StatusBadge :status="r.status" />
                            </span>
                        </div>
                        <p class="font-semibold">{{ r.title }}</p>
                        <p class="text-sm text-muted-foreground">{{ r.reporter }} · {{ r.category }} · {{ r.building }}, {{ r.room }}</p>
                        <p class="text-sm text-muted-foreground">PIC: {{ r.pic ?? '-' }} · SLA: {{ r.sla_date ? formatDateTime(r.sla_date) : '-' }}</p>
                    </Link>
                </li>
            </ul>

            <div class="hidden overflow-x-auto rounded-xl border md:block">
                <table class="w-full text-left text-sm">
                    <caption class="sr-only">Daftar laporan kerusakan</caption>
                    <thead class="bg-muted text-muted-foreground">
                        <tr>
                            <th scope="col" class="p-3 font-medium">Tiket</th>
                            <th scope="col" class="p-3 font-medium">Judul</th>
                            <th scope="col" class="p-3 font-medium">Kategori / lokasi</th>
                            <th scope="col" class="p-3 font-medium">Status</th>
                            <th scope="col" class="p-3 font-medium">PIC</th>
                            <th scope="col" class="p-3 font-medium">SLA</th>
                            <th scope="col" class="p-3 font-medium">Dibuat</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="r in reports.data" :key="r.id" class="hover:bg-accent">
                            <td class="p-3 whitespace-nowrap">
                                <Link :href="show.url(r.id)" class="font-medium text-primary underline-offset-4 hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none">{{ r.ticket_number }}</Link>
                            </td>
                            <td class="p-3">{{ r.title }}<span class="block text-muted-foreground">{{ r.reporter }}</span></td>
                            <td class="p-3">{{ r.category }}<span class="block text-muted-foreground">{{ r.building }}, {{ r.room }}</span></td>
                            <td class="p-3"><StatusBadge :status="r.status" /></td>
                            <td class="p-3">{{ r.pic ?? '-' }}</td>
                            <td class="p-3 whitespace-nowrap" :class="r.is_overdue ? 'font-semibold text-status-rejected' : ''">
                                {{ r.sla_date ? formatDateTime(r.sla_date) : '-' }}<span v-if="r.is_overdue" class="block">Terlambat</span>
                            </td>
                            <td class="p-3 whitespace-nowrap">{{ formatDate(r.created_at) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>

        <Pagination :links="reports.links" />
    </div>
</template>
```

- [ ] **Step 2: `admin/reports/Show.vue`**

```vue
<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import Timeline from '@/components/Timeline.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { formatDate, formatDateTime, formatRupiah } from '@/lib/format';
import { approve, index, reassign, reject } from '@/routes/admin/reports';

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
        reporter: string | null;
        reporter_nim_nip: string | null;
        pic: string | null;
        admin: string | null;
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
    can: { approve: boolean; reject: boolean; reassign: boolean };
    pics: { id: number; name: string; active_tasks: number }[];
    current_pic_id: number | null;
    suggested_pic_id: number | null;
    priorities: { value: string; label: string }[];
    sla_defaults: Record<string, string>;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Laporan', href: index() }] },
});

const approveForm = useForm({
    admin_priority: props.report.user_priority,
    sla_date: props.sla_defaults[props.report.user_priority] ?? '',
    assigned_pic_id: (props.suggested_pic_id ?? '') as string | number,
});

let slaTouched = false;
watch(
    () => approveForm.admin_priority,
    (priority) => {
        if (!slaTouched) approveForm.sla_date = props.sla_defaults[priority] ?? approveForm.sla_date;
    },
);

function submitApprove() {
    approveForm.post(approve.url(props.report.id), { preserveScroll: true });
}

const reassignForm = useForm({ assigned_pic_id: '' as string | number });
const otherPics = computed(() => props.pics.filter((pic) => pic.id !== props.current_pic_id));

function submitReassign() {
    reassignForm.post(reassign.url(props.report.id), { preserveScroll: true, onSuccess: () => reassignForm.reset() });
}

const rejecting = ref(false);
const rejectError = ref<string | undefined>();
const rejectBusy = ref(false);

function doReject(reason?: string) {
    rejectBusy.value = true;
    rejectError.value = undefined;
    router.post(reject.url(props.report.id), { rejection_reason: reason }, {
        preserveScroll: true,
        onError: (errors) => (rejectError.value = errors.rejection_reason),
        onSuccess: () => (rejecting.value = false),
        onFinish: () => (rejectBusy.value = false),
    });
}

const details: [string, string][] = [
    ['Pelapor', `${props.report.reporter ?? '-'} (${props.report.reporter_nim_nip ?? '-'})`],
    ['Kategori', props.report.category ?? '-'],
    ['Lokasi', `${props.report.building ?? '-'}, ${props.report.room ?? '-'}`],
    ['Aset', props.report.asset ? `${props.report.asset.name} (${props.report.asset.asset_code})` : 'Tidak dipilih'],
    ['Urgensi pelapor', props.report.user_priority],
    ['Prioritas admin', props.report.admin_priority ?? 'Belum ditentukan'],
    ['PIC', props.report.pic ?? 'Belum ditugaskan'],
    ['SLA', props.report.sla_date ? formatDateTime(props.report.sla_date) : 'Belum ditentukan'],
    ['Dibuat', formatDateTime(props.report.created_at)],
];
</script>

<template>
    <Head :title="report.ticket_number" />

    <div class="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4">
        <PageHeader :title="report.title" :description="report.ticket_number">
            <StatusBadge :status="report.status" />
            <span v-if="report.is_overdue" class="rounded-full bg-status-rejected-bg px-2.5 py-1 text-xs font-semibold text-status-rejected">Terlambat</span>
        </PageHeader>

        <dl class="grid gap-3 rounded-xl border p-4 sm:grid-cols-2">
            <div v-for="[label, value] in details" :key="label">
                <dt class="text-sm text-muted-foreground">{{ label }}</dt>
                <dd class="font-medium break-words">{{ value }}</dd>
            </div>
        </dl>

        <section aria-labelledby="desc">
            <h2 id="desc" class="mb-1 font-semibold">Deskripsi</h2>
            <p class="whitespace-pre-line">{{ report.description }}</p>
        </section>

        <section v-if="report.damage_photos.length" aria-labelledby="photos">
            <h2 id="photos" class="mb-2 font-semibold">Foto kerusakan</h2>
            <ul class="grid grid-cols-3 gap-2 sm:grid-cols-4">
                <li v-for="photo in report.damage_photos" :key="photo.id">
                    <a :href="photo.url" target="_blank" rel="noopener" class="block aspect-square overflow-hidden rounded-lg border focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none">
                        <img :src="photo.url" alt="Foto kerusakan" class="size-full object-cover" loading="lazy" />
                    </a>
                </li>
            </ul>
        </section>

        <section v-if="report.rejection_reason" class="rounded-xl border bg-status-rejected-bg p-4 text-status-rejected" role="status">
            <h2 class="font-semibold">Alasan penolakan</h2>
            <p class="mt-1 text-sm">{{ report.rejection_reason }}</p>
        </section>

        <section v-if="report.status === 'Closed'" class="grid gap-3 rounded-xl border p-4" aria-labelledby="done">
            <h2 id="done" class="font-semibold">Hasil perbaikan</h2>
            <p class="whitespace-pre-line">{{ report.repair_action }}</p>
            <p class="text-sm text-muted-foreground">
                Selesai {{ formatDate(report.completed_on) }} · Biaya {{ formatRupiah(report.repair_cost) }} · Vendor {{ report.vendor_name ?? '-' }}
            </p>
            <ul v-if="report.completion_photos.length" class="grid grid-cols-3 gap-2 sm:grid-cols-4">
                <li v-for="photo in report.completion_photos" :key="photo.id">
                    <a :href="photo.url" target="_blank" rel="noopener" class="block aspect-square overflow-hidden rounded-lg border focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none">
                        <img :src="photo.url" alt="Foto bukti perbaikan" class="size-full object-cover" loading="lazy" />
                    </a>
                </li>
            </ul>
        </section>

        <section v-if="can.approve" class="grid gap-4 rounded-xl border-2 border-primary/30 p-4" aria-labelledby="approve-h">
            <h2 id="approve-h" class="text-lg font-semibold">Validasi laporan</h2>
            <form class="grid gap-4" @submit.prevent="submitApprove">
                <div class="grid gap-2">
                    <Label for="a-priority">Prioritas</Label>
                    <NativeSelect id="a-priority" v-model="approveForm.admin_priority">
                        <option v-for="p in priorities" :key="p.value" :value="p.value">{{ p.label }}</option>
                    </NativeSelect>
                    <InputError :message="approveForm.errors.admin_priority" />
                </div>
                <div class="grid gap-2">
                    <Label for="a-sla">Target selesai (SLA)</Label>
                    <Input id="a-sla" v-model="approveForm.sla_date" type="datetime-local" class="h-11" required @input="slaTouched = true" />
                    <p class="text-xs text-muted-foreground">Terisi otomatis dari prioritas dan bisa diubah.</p>
                    <InputError :message="approveForm.errors.sla_date" />
                </div>
                <div class="grid gap-2">
                    <Label for="a-pic">PIC</Label>
                    <NativeSelect id="a-pic" v-model="approveForm.assigned_pic_id" required>
                        <option value="" disabled>Pilih PIC</option>
                        <option v-for="pic in pics" :key="pic.id" :value="pic.id">
                            {{ pic.name }} ({{ pic.active_tasks }} tugas aktif){{ pic.id === suggested_pic_id ? ' — disarankan' : '' }}
                        </option>
                    </NativeSelect>
                    <InputError :message="approveForm.errors.assigned_pic_id" />
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button type="submit" class="h-11" :disabled="approveForm.processing" data-test="approve-button">Validasi dan tugaskan</Button>
                    <Button v-if="can.reject" type="button" variant="outline" class="h-11 text-destructive" @click="rejecting = true">Tolak laporan</Button>
                </div>
            </form>
        </section>

        <section v-if="can.reassign" class="grid gap-3 rounded-xl border p-4" aria-labelledby="reassign-h">
            <h2 id="reassign-h" class="font-semibold">Pindahkan PIC</h2>
            <form class="grid gap-3 sm:grid-cols-[1fr_auto]" @submit.prevent="submitReassign">
                <div class="grid gap-2">
                    <Label for="r-pic">PIC baru</Label>
                    <NativeSelect id="r-pic" v-model="reassignForm.assigned_pic_id" required>
                        <option value="" disabled>Pilih PIC</option>
                        <option v-for="pic in otherPics" :key="pic.id" :value="pic.id">{{ pic.name }} ({{ pic.active_tasks }} tugas aktif)</option>
                    </NativeSelect>
                    <InputError :message="reassignForm.errors.assigned_pic_id" />
                </div>
                <Button type="submit" class="h-11 self-end" :disabled="reassignForm.processing">Pindahkan</Button>
            </form>
        </section>

        <section aria-labelledby="timeline">
            <h2 id="timeline" class="mb-3 font-semibold">Riwayat</h2>
            <Timeline :items="report.timeline" />
        </section>
    </div>

    <ConfirmDialog
        v-model:open="rejecting"
        title="Tolak laporan?"
        description="Pelapor akan menerima alasan penolakan. Laporan yang ditolak tidak bisa dibuka lagi."
        confirm-label="Tolak laporan"
        reason-label="Alasan penolakan"
        :reason-min="10"
        :processing="rejectBusy"
        :error="rejectError"
        destructive
        @confirm="doReject"
    />
</template>
```

- [ ] **Step 3: Nav entry.** In `useRoleNav.ts` import `ClipboardList` (already imported in Plan 3) and `index as adminReportsIndex` from `@/routes/admin/reports`; replace `adminNav` with the final ordering (bottom nav shows the first four):

```ts
const adminNav: NavItem[] = [
    { title: 'Beranda', href: adminDashboard(), icon: Home },
    { title: 'Laporan', href: adminReportsIndex(), icon: ClipboardList },
    { title: 'Pengguna', href: usersIndex(), icon: Users },
    { title: 'Aset', href: assetsIndex(), icon: Boxes },
    { title: 'Kategori', href: categoriesIndex(), icon: Tags },
    { title: 'Gedung & Ruang', href: buildingsIndex(), icon: Building2 },
    { title: 'Impor Aset', href: assetImportsIndex(), icon: Upload },
];
```

- [ ] **Step 4: Verify**

Run: `php artisan wayfinder:generate && npm run types:check && npm run check && npm run build && php artisan test`
Expected: all pass.

- [ ] **Step 5: Manual check** (admin, 1280 px and 360 px)

Run `php artisan migrate:fresh --seed && composer dev`; as `2010001` create a report; as `admin` open "Laporan".
Expected: the pending report is first; changing the priority updates the SLA unless you edited the SLA by hand; the PIC dropdown shows task counts and marks the suggested PIC; "Tolak laporan" opens the dialog and its button stays disabled until 10 characters are typed; after validation the page shows the new status and timeline entry; table shows from `md`, cards below.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat: admin report queue and detail pages (validate, reject, reassign)"
```

---

### Task 6: PIC task list and start / hold / resume

**Files:**
- Create: `app/Actions/Reports/{StartReport,HoldReport,ResumeReport}.php`, `app/Http/Requests/Pic/HoldReportRequest.php`, `app/Http/Controllers/Pic/TaskController.php`, `tests/Feature/Pic/{TaskListTest,StartHoldResumeTest}.php`
- Modify: `routes/web.php`

**Interfaces:**
- Produces: `StartReport::handle(Report, User $pic): Report`, `HoldReport::handle(Report, User $pic, string $reason): Report`, `ResumeReport::handle(Report, User $pic): Report`; routes `pic.tasks.index` (`?tab=active|done`), `pic.tasks.show`, `pic.tasks.start`, `pic.tasks.hold`, `pic.tasks.resume` (and `pic.tasks.complete` in Task 7). Index props: `tasks` (paginator), `tab`, `counts {active, done}`. Show props: `report`, `can {start, hold, resume, complete}`.

- [ ] **Step 1: Write the failing tests.** `tests/Feature/Pic/TaskListTest.php`

```php
<?php

use App\Models\Report;
use App\Models\User;

beforeEach(fn () => $this->pic = User::factory()->pic()->create());

test('a pic only sees their own tasks', function () {
    Report::factory()->count(2)->assigned($this->pic)->create();
    Report::factory()->count(3)->assigned()->create();
    Report::factory()->create(); // pending, unassigned

    $this->actingAs($this->pic)->get(route('pic.tasks.index'))
        ->assertInertia(fn ($page) => $page
            ->component('pic/tasks/Index')
            ->has('tasks.data', 2)
            ->where('tab', 'active')
            ->where('counts.active', 2)
            ->where('counts.done', 0));
});

test('active tasks are ordered by the nearest SLA, overdue first', function () {
    $later = Report::factory()->assigned($this->pic)->create(['sla_date' => now()->addDays(5)]);
    $overdue = Report::factory()->inProgress($this->pic)->create(['sla_date' => now()->subDay()]);
    $soon = Report::factory()->onHold($this->pic)->create(['sla_date' => now()->addDay()]);

    $this->actingAs($this->pic)->get(route('pic.tasks.index'))
        ->assertInertia(fn ($page) => $page
            ->where('tasks.data.0.id', $overdue->id)
            ->where('tasks.data.1.id', $soon->id)
            ->where('tasks.data.2.id', $later->id)
            ->where('tasks.data.0.is_overdue', true));
});

test('the done tab lists closed tasks, newest first', function () {
    $old = Report::factory()->closed($this->pic)->create(['closed_at' => now()->subDays(2)]);
    $new = Report::factory()->closed($this->pic)->create(['closed_at' => now()]);
    Report::factory()->assigned($this->pic)->create();

    $this->actingAs($this->pic)->get(route('pic.tasks.index', ['tab' => 'done']))
        ->assertInertia(fn ($page) => $page
            ->where('tab', 'done')
            ->has('tasks.data', 2)
            ->where('tasks.data.0.id', $new->id)
            ->where('tasks.data.1.id', $old->id)
            ->where('counts.done', 2)
            ->where('counts.active', 1));
});

test('a pic sees their task detail but not another pic’s', function () {
    $mine = Report::factory()->assigned($this->pic)->create();
    $theirs = Report::factory()->assigned()->create();

    $this->actingAs($this->pic)->get(route('pic.tasks.show', $mine))
        ->assertInertia(fn ($page) => $page
            ->component('pic/tasks/Show')
            ->where('can.start', true)
            ->where('can.hold', false)
            ->where('can.complete', false));

    $this->actingAs($this->pic)->get(route('pic.tasks.show', $theirs))->assertForbidden();
});

test('a task reassigned to someone else disappears for the previous pic', function () {
    $report = Report::factory()->inProgress($this->pic)->create();
    $admin = User::factory()->admin()->create();
    $other = User::factory()->pic()->create();

    $this->actingAs($admin)->post(route('admin.reports.reassign', $report), ['assigned_pic_id' => $other->id])->assertSessionHasNoErrors();

    $this->actingAs($this->pic)->get(route('pic.tasks.show', $report))->assertForbidden();
    $this->actingAs($this->pic)->get(route('pic.tasks.index'))->assertInertia(fn ($page) => $page->has('tasks.data', 0));
    $this->actingAs($other)->get(route('pic.tasks.show', $report))->assertOk();
});

test('reporters and admins have no task list', function (string $state) {
    $this->actingAs(User::factory()->{$state}()->create())->get(route('pic.tasks.index'))->assertForbidden();
})->with(['reporter', 'admin']);
```

`tests/Feature/Pic/StartHoldResumeTest.php`

```php
<?php

use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\User;
use App\Notifications\ReportStatusChanged;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->pic = User::factory()->pic()->create();
});

test('starting a task sets In_Progress, started_at and notifies the reporter', function () {
    $report = Report::factory()->assigned($this->pic)->create();

    $this->actingAs($this->pic)->post(route('pic.tasks.start', $report))->assertRedirect(route('pic.tasks.show', $report));

    $report->refresh();
    expect($report->status)->toBe(ReportStatus::InProgress)->and($report->started_at)->not->toBeNull();
    expect($report->statusLogs()->sole()->to_status)->toBe('In_Progress');

    Notification::assertSentTo($report->user, ReportStatusChanged::class, fn ($n) => $n->report->status === ReportStatus::InProgress);
});

test('holding needs a reason of 10+ characters and is logged each time', function () {
    $report = Report::factory()->inProgress($this->pic)->create();

    $this->actingAs($this->pic)->post(route('pic.tasks.hold', $report), [])->assertSessionHasErrors('hold_reason');
    $this->actingAs($this->pic)->post(route('pic.tasks.hold', $report), ['hold_reason' => 'pendek'])
        ->assertSessionHasErrors(['hold_reason' => 'Alasan penundaan minimal 10 karakter.']);
    expect($report->fresh()->status)->toBe(ReportStatus::InProgress);

    $this->actingAs($this->pic)->post(route('pic.tasks.hold', $report), ['hold_reason' => 'Menunggu sparepart datang.'])->assertSessionHasNoErrors();
    expect($report->fresh()->status)->toBe(ReportStatus::OnHold)->and($report->fresh()->hold_reason)->toBe('Menunggu sparepart datang.');
    Notification::assertSentTo($report->user, ReportStatusChanged::class, fn ($n) => $n->report->status === ReportStatus::OnHold);

    $this->actingAs($this->pic)->post(route('pic.tasks.resume', $report))->assertSessionHasNoErrors();
    $this->actingAs($this->pic)->post(route('pic.tasks.hold', $report), ['hold_reason' => 'Menunggu persetujuan biaya.']);

    $notes = $report->statusLogs()->pluck('note')->all();
    expect($notes)->toContain('Menunggu sparepart datang.')->toContain('Menunggu persetujuan biaya.');
});

test('resuming returns to In_Progress and keeps the original started_at', function () {
    $this->travelTo(Carbon::parse('2026-10-15 10:00', 'Asia/Jakarta'));
    $report = Report::factory()->onHold($this->pic)->create(['started_at' => now()->subDays(2)]);
    $startedAt = $report->started_at->toDateTimeString();

    $this->actingAs($this->pic)->post(route('pic.tasks.resume', $report))->assertRedirect(route('pic.tasks.show', $report));

    $report->refresh();
    expect($report->status)->toBe(ReportStatus::InProgress)
        ->and($report->started_at->toDateTimeString())->toBe($startedAt)
        ->and($report->hold_reason)->toBeNull();
});

test('starting again after a hold cycle does not move started_at', function () {
    $this->travelTo(Carbon::parse('2026-10-15 10:00', 'Asia/Jakarta'));
    $report = Report::factory()->assigned($this->pic)->create();

    $this->actingAs($this->pic)->post(route('pic.tasks.start', $report));
    $first = $report->fresh()->started_at->toDateTimeString();

    $this->travelTo(Carbon::parse('2026-10-16 10:00', 'Asia/Jakarta'));
    $this->actingAs($this->pic)->post(route('pic.tasks.hold', $report), ['hold_reason' => 'Menunggu vendor datang.']);
    $this->actingAs($this->pic)->post(route('pic.tasks.resume', $report));

    expect($report->fresh()->started_at->toDateTimeString())->toBe($first);
});

test('actions in the wrong status are refused', function (string $state, string $route) {
    $report = Report::factory()->{$state}($this->pic)->create();

    $this->actingAs($this->pic)->post(route($route, $report), ['hold_reason' => 'Alasan yang cukup panjang.'])->assertForbidden();
})->with([
    ['inProgress', 'pic.tasks.start'],
    ['assigned', 'pic.tasks.hold'],
    ['assigned', 'pic.tasks.resume'],
    ['inProgress', 'pic.tasks.resume'],
    ['onHold', 'pic.tasks.hold'],
    ['closed', 'pic.tasks.start'],
]);

test('another pic can not act on the task', function () {
    $report = Report::factory()->assigned($this->pic)->create();

    $this->actingAs(User::factory()->pic()->create())->post(route('pic.tasks.start', $report))->assertForbidden();
    expect($report->fresh()->status)->toBe(ReportStatus::Assigned);
});

test('a pic can not reject a task: there is no such route', function () {
    $report = Report::factory()->assigned($this->pic)->create();

    $this->actingAs($this->pic)->post("/pic/tasks/{$report->id}/reject", ['rejection_reason' => 'Bukan tugas saya.'])->assertNotFound();
});
```

- [ ] **Step 2: Run to verify they fail**

Run: `php artisan test tests/Feature/Pic`
Expected: FAIL (`Route [pic.tasks.index] not defined`).

- [ ] **Step 3: Actions.** `app/Actions/Reports/StartReport.php`

```php
<?php

namespace App\Actions\Reports;

use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\User;
use App\Notifications\ReportStatusChanged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

final class StartReport
{
    public function __construct(private readonly ReportTransitioner $transitioner) {}

    public function handle(Report $report, User $pic): Report
    {
        $report = DB::transaction(fn () => $this->transitioner->transition(
            $report,
            ReportStatus::InProgress,
            $pic,
            'Pengerjaan dimulai',
            ['started_at' => $report->started_at ?? now()],
        ));

        Notification::send($report->user, new ReportStatusChanged($report));

        return $report;
    }
}
```

`app/Actions/Reports/HoldReport.php`

```php
<?php

namespace App\Actions\Reports;

use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\User;
use App\Notifications\ReportStatusChanged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

final class HoldReport
{
    public function __construct(private readonly ReportTransitioner $transitioner) {}

    public function handle(Report $report, User $pic, string $reason): Report
    {
        $report = DB::transaction(fn () => $this->transitioner->transition(
            $report,
            ReportStatus::OnHold,
            $pic,
            $reason,
            ['hold_reason' => $reason],
        ));

        Notification::send($report->user, new ReportStatusChanged($report));

        return $report;
    }
}
```

`app/Actions/Reports/ResumeReport.php`

```php
<?php

namespace App\Actions\Reports;

use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\User;
use App\Notifications\ReportStatusChanged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

final class ResumeReport
{
    public function __construct(private readonly ReportTransitioner $transitioner) {}

    public function handle(Report $report, User $pic): Report
    {
        $report = DB::transaction(fn () => $this->transitioner->transition(
            $report,
            ReportStatus::InProgress,
            $pic,
            'Pengerjaan dilanjutkan',
            ['hold_reason' => null],
        ));

        Notification::send($report->user, new ReportStatusChanged($report));

        return $report;
    }
}
```

- [ ] **Step 4: Request** `app/Http/Requests/Pic/HoldReportRequest.php`

```php
<?php

namespace App\Http\Requests\Pic;

use Illuminate\Foundation\Http\FormRequest;

class HoldReportRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'hold_reason' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'hold_reason.required' => 'Alasan penundaan wajib diisi.',
            'hold_reason.min' => 'Alasan penundaan minimal 10 karakter.',
        ];
    }
}
```

- [ ] **Step 5: Controller** `app/Http/Controllers/Pic/TaskController.php` (Task 7 adds `complete`)

```php
<?php

namespace App\Http\Controllers\Pic;

use App\Actions\Reports\HoldReport;
use App\Actions\Reports\ResumeReport;
use App\Actions\Reports\StartReport;
use App\Enums\ReportStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pic\HoldReportRequest;
use App\Models\Report;
use App\Support\ReportPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    private const ACTIVE = [ReportStatus::Assigned, ReportStatus::InProgress, ReportStatus::OnHold];

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Report::class);

        $tab = $request->query('tab') === 'done' ? 'done' : 'active';
        $mine = fn () => Report::query()->visibleTo($request->user());

        $query = $mine()->with(['category:id,name', 'room.building:id,name', 'user:id,name', 'assignedPic:id,name']);

        $tab === 'done'
            ? $query->where('status', ReportStatus::Closed)->orderByDesc('closed_at')
            : $query->whereIn('status', self::ACTIVE)->orderBy('sla_date');

        return Inertia::render('pic/tasks/Index', [
            'tasks' => $query->paginate(10)->withQueryString()->through(fn (Report $report) => ReportPresenter::summary($report)),
            'tab' => $tab,
            'counts' => [
                'active' => $mine()->whereIn('status', self::ACTIVE)->count(),
                'done' => $mine()->where('status', ReportStatus::Closed)->count(),
            ],
        ]);
    }

    public function show(Report $report): Response
    {
        Gate::authorize('view', $report);

        return Inertia::render('pic/tasks/Show', [
            'report' => ReportPresenter::detail($report),
            'can' => [
                'start' => Gate::allows('start', $report),
                'hold' => Gate::allows('hold', $report),
                'resume' => Gate::allows('resume', $report),
                'complete' => Gate::allows('complete', $report),
            ],
        ]);
    }

    public function start(Request $request, Report $report, StartReport $start): RedirectResponse
    {
        Gate::authorize('start', $report);

        $start->handle($report, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pengerjaan dimulai.']);

        return to_route('pic.tasks.show', $report);
    }

    public function hold(HoldReportRequest $request, Report $report, HoldReport $hold): RedirectResponse
    {
        Gate::authorize('hold', $report);

        $hold->handle($report, $request->user(), $request->validated('hold_reason'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Tugas ditunda.']);

        return to_route('pic.tasks.show', $report);
    }

    public function resume(Request $request, Report $report, ResumeReport $resume): RedirectResponse
    {
        Gate::authorize('resume', $report);

        $resume->handle($report, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pengerjaan dilanjutkan.']);

        return to_route('pic.tasks.show', $report);
    }
}
```

- [ ] **Step 6: Routes.** In `routes/web.php` add `use App\Http\Controllers\Pic\TaskController;` and inside the `role:pic` group:

```php
        Route::get('tasks', [TaskController::class, 'index'])->name('tasks.index');
        Route::get('tasks/{report}', [TaskController::class, 'show'])->name('tasks.show');
        Route::post('tasks/{report}/start', [TaskController::class, 'start'])->name('tasks.start');
        Route::post('tasks/{report}/hold', [TaskController::class, 'hold'])->name('tasks.hold');
        Route::post('tasks/{report}/resume', [TaskController::class, 'resume'])->name('tasks.resume');
```

Create stub pages `resources/js/pages/pic/tasks/{Index,Show}.vue` (`<template><div /></template>`) so Inertia's page-exists check passes; Task 8 replaces them.

- [ ] **Step 7: Run the tests**

Run: `php artisan test tests/Feature/Pic tests/Feature/Admin`
Expected: all pass.

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "feat: PIC task list and start, hold, resume actions"
```

---

### Task 7: Complete a task

**Files:**
- Create: `app/Actions/Reports/CompleteReport.php`, `app/Http/Requests/Pic/CompleteReportRequest.php`, `tests/Feature/Pic/CompleteReportTest.php`
- Modify: `app/Http/Controllers/Pic/TaskController.php`, `routes/web.php`

**Interfaces:**
- Produces: `CompleteReport::handle(Report $report, User $pic, array $data, array $photos): Report` (`$data` keys `repair_action, completed_on, repair_cost?, vendor_name?`); route `POST pic/tasks/{report}/complete` (`pic.tasks.complete`) as multipart with `photos[]`.

- [ ] **Step 1: Write the failing test** `tests/Feature/Pic/CompleteReportTest.php`

```php
<?php

use App\Enums\AttachmentType;
use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\User;
use App\Notifications\ReportStatusChanged;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
    $this->pic = User::factory()->pic()->create();
    $this->report = Report::factory()->inProgress($this->pic)->create();
});

function completionPayload(array $overrides = []): array
{
    return array_merge([
        'repair_action' => 'Mengganti lampu proyektor dan membersihkan filter.',
        'completed_on' => now()->toDateString(),
        'repair_cost' => 350000,
        'vendor_name' => 'CV Teknik Jaya',
        'photos' => [UploadedFile::fake()->image('selesai.jpg')],
    ], $overrides);
}

test('completing closes the report with proof and notifies the reporter', function () {
    $this->actingAs($this->pic)->post(route('pic.tasks.complete', $this->report), completionPayload())
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('pic.tasks.index'));

    $report = $this->report->fresh();
    expect($report->status)->toBe(ReportStatus::Closed)
        ->and($report->repair_action)->toBe('Mengganti lampu proyektor dan membersihkan filter.')
        ->and($report->completed_on->toDateString())->toBe(now()->toDateString())
        ->and($report->repair_cost)->toBe('350000.00')
        ->and($report->vendor_name)->toBe('CV Teknik Jaya')
        ->and($report->closed_at)->not->toBeNull();

    $photo = $report->attachments()->where('type', AttachmentType::Completion)->sole();
    Storage::disk('local')->assertExists($photo->path);

    $log = $report->statusLogs()->get()->last();
    expect($log->to_status)->toBe('Closed')->and($log->actor_id)->toBe($this->pic->id);

    Notification::assertSentTo($report->user, ReportStatusChanged::class, fn ($n) => $n->report->status === ReportStatus::Closed);
});

test('cost and vendor are optional', function () {
    $this->actingAs($this->pic)->post(route('pic.tasks.complete', $this->report), completionPayload(['repair_cost' => null, 'vendor_name' => null]))
        ->assertSessionHasNoErrors();

    $report = $this->report->fresh();
    expect($report->repair_cost)->toBeNull()->and($report->vendor_name)->toBeNull()->and($report->status)->toBe(ReportStatus::Closed);
});

test('repair cost accepts zero and decimals but not negatives', function () {
    $post = fn ($cost) => $this->actingAs($this->pic)->post(route('pic.tasks.complete', $this->report), completionPayload(['repair_cost' => $cost]));

    $post(-1)->assertSessionHasErrors('repair_cost');
    $post('abc')->assertSessionHasErrors('repair_cost');
    expect($this->report->fresh()->status)->toBe(ReportStatus::InProgress);

    $post('1500000.50')->assertSessionHasNoErrors();
    expect($this->report->fresh()->repair_cost)->toBe('1500000.50');
});

test('zero is a valid cost', function () {
    $this->actingAs($this->pic)->post(route('pic.tasks.complete', $this->report), completionPayload(['repair_cost' => 0]))->assertSessionHasNoErrors();

    expect($this->report->fresh()->repair_cost)->toBe('0.00');
});

test('the repair action is required', function () {
    $this->actingAs($this->pic)->post(route('pic.tasks.complete', $this->report), completionPayload(['repair_action' => '']))
        ->assertSessionHasErrors(['repair_action' => 'Tindakan perbaikan wajib diisi.']);
});

test('at least one photo and at most three are required', function () {
    $post = fn (array $photos) => $this->actingAs($this->pic)->post(route('pic.tasks.complete', $this->report), completionPayload(['photos' => $photos]));
    $image = fn (string $name) => UploadedFile::fake()->image($name);

    $post([])->assertSessionHasErrors(['photos' => 'Minimal 1 foto bukti pengerjaan.']);
    $post([$image('a.jpg'), $image('b.jpg'), $image('c.jpg'), $image('d.jpg')])->assertSessionHasErrors(['photos' => 'Maksimal 3 foto.']);
    $post([UploadedFile::fake()->create('x.pdf', 5, 'application/pdf')])->assertSessionHasErrors('photos.0');

    expect($this->report->fresh()->status)->toBe(ReportStatus::InProgress);
});

test('the completion date can not be in the future or before the report was created', function () {
    $post = fn (string $date) => $this->actingAs($this->pic)->post(route('pic.tasks.complete', $this->report), completionPayload(['completed_on' => $date]));

    $post(now()->addDay()->toDateString())->assertSessionHasErrors('completed_on');
    $post($this->report->created_at->copy()->subDay()->toDateString())->assertSessionHasErrors('completed_on');

    expect($this->report->fresh()->status)->toBe(ReportStatus::InProgress);

    $post($this->report->created_at->toDateString())->assertSessionHasNoErrors();
});

test('only tasks in progress can be completed', function (string $state) {
    $report = Report::factory()->{$state}($this->pic)->create();

    $this->actingAs($this->pic)->post(route('pic.tasks.complete', $report), completionPayload())->assertForbidden();

    expect($report->fresh()->status->value)->not->toBe('Closed');
})->with(['assigned', 'onHold']);

test('a closed task can not be completed twice', function () {
    $this->actingAs($this->pic)->post(route('pic.tasks.complete', $this->report), completionPayload());
    $this->actingAs($this->pic)->post(route('pic.tasks.complete', $this->report), completionPayload(['repair_action' => 'Tindakan kedua yang berbeda.']))->assertForbidden();

    expect($this->report->fresh()->repair_action)->toBe('Mengganti lampu proyektor dan membersihkan filter.');
});

test('another pic can not complete the task', function () {
    $this->actingAs(User::factory()->pic()->create())->post(route('pic.tasks.complete', $this->report), completionPayload())->assertForbidden();
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `php artisan test tests/Feature/Pic/CompleteReportTest.php`
Expected: FAIL (`Route [pic.tasks.complete] not defined`).

- [ ] **Step 3: Action** `app/Actions/Reports/CompleteReport.php`

```php
<?php

namespace App\Actions\Reports;

use App\Enums\AttachmentType;
use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\User;
use App\Notifications\ReportStatusChanged;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

final class CompleteReport
{
    public function __construct(
        private readonly ReportTransitioner $transitioner,
        private readonly AttachmentStore $attachments,
    ) {}

    /**
     * @param  array{repair_action: string, completed_on: string, repair_cost?: mixed, vendor_name?: string|null}  $data
     * @param  array<int, UploadedFile>  $photos
     */
    public function handle(Report $report, User $pic, array $data, array $photos): Report
    {
        $report = DB::transaction(function () use ($report, $pic, $data, $photos) {
            $this->transitioner->transition($report, ReportStatus::Closed, $pic, 'Perbaikan selesai', [
                'repair_action' => $data['repair_action'],
                'completed_on' => $data['completed_on'],
                'repair_cost' => $data['repair_cost'] ?? null,
                'vendor_name' => $data['vendor_name'] ?? null,
                'closed_at' => now(),
            ]);

            $this->attachments->add($report, AttachmentType::Completion, $photos);

            return $report;
        });

        Notification::send($report->user, new ReportStatusChanged($report));

        return $report;
    }
}
```

- [ ] **Step 4: Request** `app/Http/Requests/Pic/CompleteReportRequest.php`

```php
<?php

namespace App\Http\Requests\Pic;

use Illuminate\Foundation\Http\FormRequest;

class CompleteReportRequest extends FormRequest
{
    public function rules(): array
    {
        $photos = config('siperkasa.photos');
        $created = $this->route('report')->created_at->toDateString();

        return [
            'repair_action' => ['required', 'string', 'min:5', 'max:2000'],
            'completed_on' => ['required', 'date', 'before_or_equal:today', "after_or_equal:{$created}"],
            'repair_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'vendor_name' => ['nullable', 'string', 'max:150'],
            'photos' => ['required', 'array', "min:{$photos['min']}", "max:{$photos['max']}"],
            'photos.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', "max:{$photos['max_kb']}"],
        ];
    }

    public function messages(): array
    {
        return [
            'repair_action.required' => 'Tindakan perbaikan wajib diisi.',
            'repair_action.min' => 'Tindakan perbaikan minimal 5 karakter.',
            'completed_on.before_or_equal' => 'Tanggal selesai tidak boleh di masa depan.',
            'completed_on.after_or_equal' => 'Tanggal selesai tidak boleh sebelum laporan dibuat.',
            'repair_cost.min' => 'Biaya tidak boleh negatif.',
            'repair_cost.numeric' => 'Biaya harus berupa angka.',
            'photos.required' => 'Minimal 1 foto bukti pengerjaan.',
            'photos.min' => 'Minimal 1 foto bukti pengerjaan.',
            'photos.max' => 'Maksimal 3 foto.',
            'photos.*.image' => 'Berkas harus berupa gambar.',
            'photos.*.mimes' => 'Foto harus berformat jpg, png, atau webp.',
            'photos.*.max' => 'Ukuran foto maksimal 2 MB.',
        ];
    }
}
```

- [ ] **Step 5: Controller method and route.** In `Pic\TaskController` add `use App\Actions\Reports\CompleteReport; use App\Http\Requests\Pic\CompleteReportRequest;` and:

```php
    public function complete(CompleteReportRequest $request, Report $report, CompleteReport $complete): RedirectResponse
    {
        Gate::authorize('complete', $report);

        $complete->handle(
            $report,
            $request->user(),
            $request->safe()->except('photos'),
            $request->file('photos', []),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => "Laporan {$report->ticket_number} selesai."]);

        return to_route('pic.tasks.index');
    }
```

Route (inside the `role:pic` group): `Route::post('tasks/{report}/complete', [TaskController::class, 'complete'])->name('tasks.complete');`

`FormRequest` runs before `Gate::authorize`, so a PIC posting to a report that is not theirs still gets `403` (from the policy) after validation passes; an invalid payload gets validation errors first — neither changes any data.

- [ ] **Step 6: Run the tests**

Run: `php artisan test`
Expected: all pass.

- [ ] **Step 7: Lint, types and commit**

Run: `composer lint:check && composer types:check`
Expected: clean.

```bash
git add -A
git commit -m "feat: PIC completes a task with repair details and photo proof"
```

---

### Task 8: PIC pages

**Files:**
- Replace stubs: `resources/js/pages/pic/tasks/{Index,Show}.vue`
- Modify: `resources/js/composables/useRoleNav.ts`

**Interfaces:**
- Consumes `PhotoUploader` (v-model `File[]`, prop `error`), `Timeline`, `StatusBadge`, `ConfirmDialog` (with reason), `Textarea`, `NativeSelect`.

- [ ] **Step 1: `pic/tasks/Index.vue`**

```vue
<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { formatDate, formatDateTime } from '@/lib/format';
import { index, show } from '@/routes/pic/tasks';

type Row = {
    id: number;
    ticket_number: string;
    title: string;
    status: string;
    admin_priority: string | null;
    is_overdue: boolean;
    sla_date: string | null;
    created_at: string;
    category: string | null;
    room: string | null;
    building: string | null;
    reporter: string | null;
};

defineProps<{
    tasks: { data: Row[]; links: { url: string | null; label: string; active: boolean }[] };
    tab: 'active' | 'done';
    counts: { active: number; done: number };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Tugas', href: index() }] },
});
</script>

<template>
    <Head title="Tugas Saya" />

    <div class="flex flex-col gap-4 p-4">
        <PageHeader title="Tugas Saya" description="Daftar laporan yang ditugaskan kepada Anda, urut dari SLA terdekat." />

        <div class="flex gap-2" role="tablist" aria-label="Jenis tugas">
            <Button as-child class="h-11" :variant="tab === 'active' ? 'default' : 'outline'">
                <Link :href="index.url()" role="tab" :aria-selected="tab === 'active'">Aktif ({{ counts.active }})</Link>
            </Button>
            <Button as-child class="h-11" :variant="tab === 'done' ? 'default' : 'outline'">
                <Link :href="index.url({ query: { tab: 'done' } })" role="tab" :aria-selected="tab === 'done'">Selesai ({{ counts.done }})</Link>
            </Button>
        </div>

        <EmptyState
            v-if="tasks.data.length === 0"
            :title="tab === 'active' ? 'Tidak ada tugas aktif' : 'Belum ada tugas selesai'"
            :description="tab === 'active' ? 'Tugas baru dari admin akan muncul di sini.' : 'Tugas yang sudah Anda selesaikan akan tampil di sini.'"
        />

        <ul v-else class="grid gap-3">
            <li v-for="task in tasks.data" :key="task.id">
                <Link
                    :href="show.url(task.id)"
                    class="grid gap-2 rounded-xl border p-4 hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    :class="task.is_overdue ? 'border-status-rejected' : ''"
                >
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <span class="text-sm font-medium text-muted-foreground">{{ task.ticket_number }}</span>
                        <span class="flex items-center gap-2">
                            <span v-if="task.is_overdue" class="rounded-full bg-status-rejected-bg px-2.5 py-1 text-xs font-semibold text-status-rejected">Terlambat</span>
                            <StatusBadge :status="task.status" />
                        </span>
                    </div>
                    <p class="font-semibold">{{ task.title }}</p>
                    <p class="text-sm text-muted-foreground">
                        {{ task.category ?? '-' }} · {{ task.building ?? '-' }}, {{ task.room ?? '-' }}
                    </p>
                    <p class="text-sm" :class="task.is_overdue ? 'font-semibold text-status-rejected' : 'text-muted-foreground'">
                        SLA: {{ task.sla_date ? formatDateTime(task.sla_date) : '-' }} · Dibuat {{ formatDate(task.created_at) }}
                    </p>
                </Link>
            </li>
        </ul>

        <Pagination :links="tasks.links" />
    </div>
</template>
```

- [ ] **Step 2: `pic/tasks/Show.vue`**

```vue
<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import PhotoUploader from '@/components/PhotoUploader.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import Timeline from '@/components/Timeline.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { formatDateTime } from '@/lib/format';
import { complete, hold, index, resume, start } from '@/routes/pic/tasks';

type Photo = { id: number; url: string };

const props = defineProps<{
    report: {
        id: number;
        ticket_number: string;
        title: string;
        description: string;
        status: string;
        admin_priority: string | null;
        is_overdue: boolean;
        sla_date: string | null;
        created_at: string;
        category: string | null;
        room: string | null;
        building: string | null;
        reporter: string | null;
        asset: { asset_code: string; name: string } | null;
        hold_reason: string | null;
        damage_photos: Photo[];
        timeline: { id: number; to_status: string; to_label: string; actor: string | null; note: string | null; created_at: string }[];
    };
    can: { start: boolean; hold: boolean; resume: boolean; complete: boolean };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Tugas', href: index() }] },
});

function post(url: string) {
    router.post(url, {}, { preserveScroll: true });
}

const holding = ref(false);
const holdError = ref<string | undefined>();
const holdBusy = ref(false);

function doHold(reason?: string) {
    holdBusy.value = true;
    holdError.value = undefined;
    router.post(hold.url(props.report.id), { hold_reason: reason }, {
        preserveScroll: true,
        onError: (errors) => (holdError.value = errors.hold_reason),
        onSuccess: () => (holding.value = false),
        onFinish: () => (holdBusy.value = false),
    });
}

// YYYY-MM-DD in Jakarta time (matches the server's "today").
const today = new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Jakarta' }).format(new Date());
const form = useForm({
    repair_action: '',
    completed_on: today,
    repair_cost: '' as string | number,
    vendor_name: '',
    photos: [] as File[],
});

const confirmComplete = ref(false);

function submitComplete() {
    form.post(complete.url(props.report.id), {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => (confirmComplete.value = false),
    });
}

const details: [string, string][] = [
    ['Pelapor', props.report.reporter ?? '-'],
    ['Kategori', props.report.category ?? '-'],
    ['Lokasi', `${props.report.building ?? '-'}, ${props.report.room ?? '-'}`],
    ['Aset', props.report.asset ? `${props.report.asset.name} (${props.report.asset.asset_code})` : 'Tidak dipilih'],
    ['Prioritas', props.report.admin_priority ?? '-'],
    ['SLA', props.report.sla_date ? formatDateTime(props.report.sla_date) : '-'],
];
</script>

<template>
    <Head :title="report.ticket_number" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4">
        <PageHeader :title="report.title" :description="report.ticket_number">
            <StatusBadge :status="report.status" />
            <span v-if="report.is_overdue" class="rounded-full bg-status-rejected-bg px-2.5 py-1 text-xs font-semibold text-status-rejected">Terlambat</span>
        </PageHeader>

        <section v-if="report.hold_reason && report.status === 'On_Hold'" class="rounded-xl border bg-status-on-hold-bg p-4 text-status-on-hold" role="status">
            <h2 class="font-semibold">Ditunda</h2>
            <p class="mt-1 text-sm">{{ report.hold_reason }}</p>
        </section>

        <dl class="grid gap-3 rounded-xl border p-4 sm:grid-cols-2">
            <div v-for="[label, value] in details" :key="label">
                <dt class="text-sm text-muted-foreground">{{ label }}</dt>
                <dd class="font-medium break-words">{{ value }}</dd>
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

        <div v-if="can.start || can.hold || can.resume" class="flex flex-wrap gap-2">
            <Button v-if="can.start" class="h-11" data-test="start-button" @click="post(start.url(report.id))">Mulai kerjakan</Button>
            <Button v-if="can.hold" variant="outline" class="h-11" data-test="hold-button" @click="holding = true">Tunda (ada kendala)</Button>
            <Button v-if="can.resume" class="h-11" data-test="resume-button" @click="post(resume.url(report.id))">Lanjutkan pengerjaan</Button>
        </div>

        <section v-if="can.complete" class="grid gap-4 rounded-xl border-2 border-primary/30 p-4" aria-labelledby="complete-h">
            <h2 id="complete-h" class="text-lg font-semibold">Form penyelesaian</h2>
            <form class="grid gap-4" @submit.prevent="confirmComplete = true">
                <div class="grid gap-2">
                    <Label for="c-action">Tindakan perbaikan</Label>
                    <Textarea id="c-action" v-model="form.repair_action" :rows="4" required />
                    <InputError :message="form.errors.repair_action" />
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="c-date">Tanggal selesai</Label>
                        <Input id="c-date" v-model="form.completed_on" type="date" :max="today" class="h-11" required />
                        <InputError :message="form.errors.completed_on" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="c-cost">Biaya (Rp, opsional)</Label>
                        <Input id="c-cost" v-model="form.repair_cost" type="number" inputmode="decimal" min="0" step="0.01" class="h-11" />
                        <InputError :message="form.errors.repair_cost" />
                    </div>
                </div>
                <div class="grid gap-2">
                    <Label for="c-vendor">Vendor / teknisi luar (opsional)</Label>
                    <Input id="c-vendor" v-model="form.vendor_name" class="h-11" />
                    <InputError :message="form.errors.vendor_name" />
                </div>
                <div class="grid gap-2">
                    <Label>Foto bukti pengerjaan</Label>
                    <PhotoUploader v-model="form.photos" :error="form.errors.photos ?? form.errors['photos.0' as keyof typeof form.errors]" />
                </div>
                <Button type="submit" class="h-11" :disabled="form.processing" data-test="complete-button">Selesaikan laporan</Button>
            </form>
        </section>

        <section aria-labelledby="timeline">
            <h2 id="timeline" class="mb-3 font-semibold">Riwayat</h2>
            <Timeline :items="report.timeline" />
        </section>
    </div>

    <ConfirmDialog
        v-model:open="holding"
        title="Tunda pengerjaan?"
        description="Jelaskan kendalanya. Pelapor akan melihat status tertunda."
        confirm-label="Tunda"
        reason-label="Alasan penundaan"
        :reason-min="10"
        :processing="holdBusy"
        :error="holdError"
        @confirm="doHold"
    />

    <ConfirmDialog
        v-model:open="confirmComplete"
        title="Selesaikan laporan?"
        description="Setelah diselesaikan, laporan ditutup dan pelapor diberi tahu. Ini tidak bisa dibatalkan."
        confirm-label="Ya, selesaikan"
        :processing="form.processing"
        @confirm="submitComplete"
    />
</template>
```
- [ ] **Step 3: Nav entry.** In `useRoleNav.ts` import `ListChecks` from `@lucide/vue` and `index as picTasksIndex` from `@/routes/pic/tasks`; replace `picNav`:

```ts
const picNav: NavItem[] = [
    { title: 'Beranda', href: picDashboard(), icon: Home },
    { title: 'Tugas', href: picTasksIndex(), icon: ListChecks },
];
```

- [ ] **Step 4: Verify the whole plan**

Run: `php artisan test && composer lint:check && composer types:check && php artisan wayfinder:generate && npm run types:check && npm run check && npm run build`
Expected: all pass.

- [ ] **Step 5: Manual end-to-end check (use three browser profiles or private windows)**

1. Reporter `2010001`: create a report with a photo.
2. Admin `admin`: open "Laporan" → the report is first → validate (High, default SLA, PIC `pic001`).
3. PIC `pic001`: "Tugas" shows the task → "Mulai kerjakan" → "Tunda" with a reason → "Lanjutkan" → fill the completion form with a photo → confirm.
4. Reporter: open the report: status "Selesai", repair details and proof photo visible, timeline has every step.
5. Admin: reassign a second report to another PIC; the old PIC no longer sees it.

Expected: every step works at 360 px and 1280 px, dialogs trap focus and close with Esc, no horizontal page scroll.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat: PIC task list and detail pages with completion form"
```

---

## Self-Review (Plan 4)

- **Spec coverage:** F06 AC1 (SLA from priority, editable) → Task 2 (`sla_defaults`) + Task 5 UI; AC2 (default PIC) → Task 2; AC3 (validate fields, log, notifications) → Task 2; AC4 (past SLA / non-pic) → Task 2; AC5 (reject reason + notification with reason) → Task 3; AC6 (reassign) → Task 4 + Task 6 `TaskListTest`; AC7 (final statuses refused) → Tasks 3–4; AC8 (overdue filter) → Task 1. F07 AC1 (own tasks only) → Task 6; AC2 (start + `started_at` + notification) → Task 6; AC3 (hold reason) → Task 6; AC4 (resume keeps `started_at`) → Task 6; AC5 (two holds logged) → Task 6; AC6 (form validation) → Task 7; AC7 (close + redirect + notification) → Task 7; AC8 (cannot complete from `Assigned`) → Task 7; AC9 (other PIC 403) → Tasks 6–7. F08 recipients for validate/reassign/reject/start/hold/resume/complete → each task's tests.
- **Placeholder scan:** none.
- **Type consistency:** action signatures match Global Interfaces in `00-index`; `ReportPresenter::summary/detail`, `Timeline`, `PhotoUploader` are used exactly as defined in Plan 3; `ReportFilter` is consumed by Plan 5.
- **Review Focus:** all five lines have tests (Tasks 2, 4, 7).
