# SiPerkasa Plan 5 — Notifications UI, Dashboards & Export

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Every role sees a bell with unread notifications, lands on a role-specific dashboard with real numbers, and admins download filtered report exports (Excel and PDF). A demo seeder fills a local database so all of this can be seen.

**Architecture:** Laravel database notifications (created in Plans 3–4) are exposed through a small `NotificationController` and an Inertia shared prop for the badge. A `DashboardStats` service owns every dashboard query. Exports reuse `ReportFilter` (Plan 4); Excel is streamed with OpenSpout (installed in Plan 2) and PDF is rendered with dompdf from a Blade view.

**Tech Stack:** Laravel 13, openspout/openspout, barryvdh/laravel-dompdf (new), Inertia 3 + Vue 3 + TypeScript, Pest 5, MySQL 8.

**Spec:** `C:\Users\Lenovo\Documents\Obsidian Vault\40 - PROJECTS\SiPerkasa\04-Spec.md` (also `03-Tasks/F08-Notifikasi.md`, `F09-Dashboard.md`, `F10-Export.md`). Requires Plans 1–4.

## Global Constraints

- Notifications: channel `database`; payload keys `report_id, ticket_number, status, message, url`; badge shows the unread count (displayed as `99+` above 99); polling every 60 s; a notification can only be read by its recipient (others get 404); reading redirects to its `url`.
- Dashboards: user (Menunggu, Diproses = Assigned+In_Progress+On_Hold, Selesai, Ditolak/Dibatalkan + 5 latest); PIC (new, in progress, on hold, overdue, done this month + 5 nearest SLA); admin (count per status, overdue, waiting validation, per category, per building, 12-week trend, average completion and validation time, PIC load, date filter default last 30 days). Admin statistics are cached 5 minutes. Cards and bars link to the filtered report list.
- Admin date range is inclusive: a report created at 23:30 on the `to` date is included. Averages are `null` (not 0) when there is nothing to average.
- Excel export: columns in the order of `ReportExport::HEADERS`; all rows matching the filter; file name `laporan-siperkasa-YYYYMMDD-HHmm.xlsx`; a file with no matches still has the header plus a "Tidak ada data" row; cells starting with `= + - @` (or tab/CR) are prefixed with `'` (formula-injection guard).
- PDF export: A4 landscape, UINSSC logo header, filter summary, count per status, table; refuses more than `config('siperkasa.pdf_max_rows')` (1000) rows with an error on `export`.
- Only admins export. Filters come from `ReportFilter` (same query string as the list).
- UI: Bahasa Indonesia, tokens only, touch targets ≥ 44 px; charts are plain CSS bars with text values and a screen-reader table (no chart library).
- Commit messages must not contain any Claude attribution or `Co-Authored-By` trailer.

## Review Focus

- Clicking the notification of a task that has since been reassigned still marks it read and redirects (the destination then shows 403), without an error (Task 1).
- A report created at 23:30 on the last day of the range is counted; the averages are `null` on an empty database (Task 2).
- A report title like `=HYPERLINK("http://evil")` is exported as text, not as a formula (Task 3).
- An export with filters that match nothing returns a valid workbook with the "Tidak ada data" row (Task 3).
- A PDF request above the row limit is refused with a clear message, not a timeout (Task 3).

## File Structure

| File | Responsibility |
|---|---|
| `app/Http/Controllers/NotificationController.php` | Inbox, recent (JSON), mark read, mark all read |
| `app/Support/DashboardStats.php` | All dashboard queries |
| `app/Http/Controllers/{User,Pic,Admin}/DashboardController.php` | Dashboard pages |
| `app/Support/ReportExport.php` | Export headers, row mapping, sanitising, filter text |
| `app/Http/Controllers/Admin/ExportController.php`, `resources/views/exports/reports.blade.php` | Excel and PDF |
| `database/seeders/DemoReportSeeder.php` | Local demo data |
| `resources/js/components/{NotificationBell,StatCard,BarList,WeeklyTrend}.vue` | UI building blocks |

---

### Task 1: Notification inbox, bell and badge

**Files:**
- Create: `app/Http/Controllers/NotificationController.php`, `resources/js/components/NotificationBell.vue`, `resources/js/pages/notifications/Index.vue`, `tests/Feature/Notifications/{NotificationInboxTest,ReportLifecycleNotificationsTest}.php`
- Modify: `app/Http/Middleware/HandleInertiaRequests.php`, `routes/web.php`, `resources/js/components/AppSidebarHeader.vue`, `resources/js/types/global.d.ts`, `resources/js/composables/useRoleNav.ts`

**Interfaces:**
- Produces: routes `notifications.index`, `notifications.recent` (JSON `{data: Item[]}`, max 10), `notifications.read` (`POST notifications/{id}/read`), `notifications.read-all` (`POST notifications/read-all`); shared prop `unreadNotificationsCount: number`. `Item` = `{ id, report_id, ticket_number, status, message, url, read: boolean, created_at }`.

- [ ] **Step 1: Write the failing tests.** `tests/Feature/Notifications/NotificationInboxTest.php`

```php
<?php

use App\Models\Report;
use App\Models\User;
use App\Notifications\ReportAssigned;
use App\Notifications\ReportStatusChanged;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->report = Report::factory()->for($this->user)->create();
});

function notifyTimes(User $user, Report $report, int $times): void
{
    foreach (range(1, $times) as $ignored) {
        $user->notify(new ReportStatusChanged($report));
    }
}

test('the badge count is shared with every page', function () {
    notifyTimes($this->user, $this->report, 3);

    $this->actingAs($this->user)->get(route('user.dashboard'))
        ->assertInertia(fn ($page) => $page->where('unreadNotificationsCount', 3));
});

test('the inbox lists only my notifications with the standard payload', function () {
    notifyTimes($this->user, $this->report, 3);
    notifyTimes(User::factory()->create(), Report::factory()->create(), 2);

    $this->actingAs($this->user)->get(route('notifications.index'))
        ->assertInertia(fn ($page) => $page
            ->component('notifications/Index')
            ->has('notifications.data', 3)
            ->where('notifications.data.0.read', false)
            ->where('notifications.data.0.ticket_number', $this->report->ticket_number)
            ->has('notifications.data.0.message')
            ->has('notifications.data.0.url')
            ->has('notifications.links'));
});

test('the inbox is paginated at 15', function () {
    notifyTimes($this->user, $this->report, 17);

    $this->actingAs($this->user)->get(route('notifications.index'))
        ->assertInertia(fn ($page) => $page->has('notifications.data', 15));
});

test('the recent endpoint returns at most 10 items as JSON', function () {
    notifyTimes($this->user, $this->report, 12);

    $this->actingAs($this->user)->getJson(route('notifications.recent'))
        ->assertOk()
        ->assertJsonCount(10, 'data')
        ->assertJsonStructure(['data' => [['id', 'report_id', 'ticket_number', 'status', 'message', 'url', 'read', 'created_at']]]);
});

test('reading a notification marks it read and opens its report', function () {
    $this->user->notify(new ReportStatusChanged($this->report));
    $notification = $this->user->notifications()->first();

    $this->actingAs($this->user)->post(route('notifications.read', $notification->id))
        ->assertRedirect(route('reports.show', $this->report));

    expect($notification->fresh()->read_at)->not->toBeNull();
    expect($this->user->unreadNotifications()->count())->toBe(0);
});

test('nobody can read someone else’s notification', function () {
    $this->user->notify(new ReportStatusChanged($this->report));
    $notification = $this->user->notifications()->first();

    $this->actingAs(User::factory()->create())->post(route('notifications.read', $notification->id))->assertNotFound();

    expect($notification->fresh()->read_at)->toBeNull();
});

test('mark all read clears the badge', function () {
    notifyTimes($this->user, $this->report, 4);

    $this->actingAs($this->user)->post(route('notifications.read-all'))->assertRedirect();

    expect($this->user->unreadNotifications()->count())->toBe(0);
});

test('reading the notification of a task that was reassigned still works', function () {
    $pic = User::factory()->pic()->create();
    $report = Report::factory()->assigned($pic)->create();
    $pic->notify(new ReportAssigned($report));
    $notification = $pic->notifications()->first();

    $report->update(['assigned_pic_id' => User::factory()->pic()->create()->id]);

    $this->actingAs($pic)->post(route('notifications.read', $notification->id))
        ->assertRedirect(route('pic.tasks.show', $report));

    expect($notification->fresh()->read_at)->not->toBeNull();
    $this->actingAs($pic)->get(route('pic.tasks.show', $report))->assertForbidden();
});

test('guests are sent to the login page', function () {
    $this->get(route('notifications.index'))->assertRedirect(route('login'));
    $this->getJson(route('notifications.recent'))->assertUnauthorized();
});
```

`tests/Feature/Notifications/ReportLifecycleNotificationsTest.php` (no fakes: real database notifications across the whole flow)

```php
<?php

use App\Models\Category;
use App\Models\Report;
use App\Models\Room;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('every step of a report notifies the right people with the right link', function () {
    Storage::fake('local');

    $reporter = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $otherAdmin = User::factory()->admin()->inactive()->create();
    $pic = User::factory()->pic()->create();
    $unrelatedPic = User::factory()->pic()->create();

    $unread = fn (User $user) => $user->unreadNotifications()->count();

    // 1. the reporter files a report → active admins are told
    $this->actingAs($reporter)->post(route('reports.store'), [
        'title' => 'Proyektor mati',
        'category_id' => Category::factory()->create()->id,
        'room_id' => Room::factory()->create()->id,
        'description' => 'Proyektor ruang 101 tidak menyala.',
        'user_priority' => 'High',
        'photos' => [UploadedFile::fake()->image('a.jpg')],
    ])->assertSessionHasNoErrors();

    $report = Report::sole();

    expect($unread($admin))->toBe(1)
        ->and($unread($otherAdmin))->toBe(0)
        ->and($unread($reporter))->toBe(0);
    expect($admin->notifications()->first()->data['url'])->toBe(route('admin.reports.show', $report));
    expect($admin->notifications()->first()->data['message'])->toContain($report->ticket_number);

    // 2. the admin validates → the pic and the reporter are told
    $this->actingAs($admin)->post(route('admin.reports.approve', $report), [
        'admin_priority' => 'High',
        'sla_date' => now()->addDays(3)->format('Y-m-d\TH:i'),
        'assigned_pic_id' => $pic->id,
    ])->assertSessionHasNoErrors();

    expect($unread($pic))->toBe(1)->and($unread($reporter))->toBe(1)->and($unread($unrelatedPic))->toBe(0);
    expect($pic->notifications()->first()->data['url'])->toBe(route('pic.tasks.show', $report));

    // 3. the pic works on it → only the reporter is told
    $this->actingAs($pic)->post(route('pic.tasks.start', $report));
    $this->actingAs($pic)->post(route('pic.tasks.hold', $report), ['hold_reason' => 'Menunggu sparepart datang.']);
    $this->actingAs($pic)->post(route('pic.tasks.resume', $report));
    $this->actingAs($pic)->post(route('pic.tasks.complete', $report), [
        'repair_action' => 'Mengganti lampu proyektor.',
        'completed_on' => now()->toDateString(),
        'photos' => [UploadedFile::fake()->image('selesai.jpg')],
    ])->assertSessionHasNoErrors();

    expect($unread($reporter))->toBe(5)   // assigned, started, held, resumed, closed
        ->and($unread($pic))->toBe(1)
        ->and($unread($admin))->toBe(1);

    $latest = $reporter->notifications()->first();
    expect($latest->data['status'])->toBe('Closed')
        ->and($latest->data['url'])->toBe(route('reports.show', $report))
        ->and($latest->data['message'])->toBe("Laporan {$report->ticket_number} sudah selesai.");
});
```

- [ ] **Step 2: Run to verify they fail**

Run: `php artisan test tests/Feature/Notifications/NotificationInboxTest.php tests/Feature/Notifications/ReportLifecycleNotificationsTest.php`
Expected: FAIL (`Route [notifications.index] not defined`).

- [ ] **Step 3: Controller** `app/Http/Controllers/NotificationController.php`

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('notifications/Index', [
            'notifications' => $request->user()->notifications()->paginate(15)
                ->through(fn (DatabaseNotification $notification) => self::present($notification)),
        ]);
    }

    public function recent(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $request->user()->notifications()->limit(10)->get()
                ->map(fn (DatabaseNotification $notification) => self::present($notification))->values(),
        ]);
    }

    public function read(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        $url = (string) ($notification->data['url'] ?? '');

        return redirect($url !== '' && str_starts_with($url, url('/')) ? $url : route('dashboard'));
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back();
    }

    /** @return array<string, mixed> */
    private static function present(DatabaseNotification $notification): array
    {
        $data = $notification->data;

        return [
            'id' => $notification->id,
            'report_id' => $data['report_id'] ?? null,
            'ticket_number' => $data['ticket_number'] ?? null,
            'status' => $data['status'] ?? null,
            'message' => $data['message'] ?? '',
            'url' => $data['url'] ?? null,
            'read' => $notification->read_at !== null,
            'created_at' => $notification->created_at?->toIso8601String(),
        ];
    }
}
```

- [ ] **Step 4: Routes.** In `routes/web.php` add `use App\Http\Controllers\NotificationController;` and inside the `['auth','active']` group (outside the role groups):

```php
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('notifications/recent', [NotificationController::class, 'recent'])->name('notifications.recent');
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('notifications/{id}/read', [NotificationController::class, 'read'])->name('notifications.read');
```

- [ ] **Step 5: Shared badge count.** In `app/Http/Middleware/HandleInertiaRequests.php` `share()` add, next to `'sidebarOpen'`:

```php
            'unreadNotificationsCount' => fn () => $request->user()?->unreadNotifications()->count() ?? 0,
```

In `resources/js/types/global.d.ts` add to `sharedPageProps`: `unreadNotificationsCount: number;`.

- [ ] **Step 6: Run the tests**

Run: `php artisan test tests/Feature/Notifications`
Expected: all pass. Before running, create the stub page `resources/js/pages/notifications/Index.vue` containing only `<template><div /></template>` (Inertia checks that page files exist). Step 8 replaces it.

- [ ] **Step 7: `NotificationBell.vue`**

```vue
<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Bell } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { formatDateTime } from '@/lib/format';
import { index, read, readAll, recent } from '@/routes/notifications';

type Item = { id: string; ticket_number: string | null; message: string; read: boolean; created_at: string };

const page = usePage();
const count = computed(() => page.props.unreadNotificationsCount ?? 0);
const label = computed(() => (count.value > 99 ? '99+' : String(count.value)));

const items = ref<Item[]>([]);
const loading = ref(false);

async function loadRecent(open: boolean) {
    if (!open) return;

    loading.value = true;
    try {
        const response = await fetch(recent.url(), { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        if (response.ok) items.value = ((await response.json()) as { data: Item[] }).data;
    } finally {
        loading.value = false;
    }
}

function openItem(item: Item) {
    router.post(read.url(item.id));
}

let timer: ReturnType<typeof setInterval> | undefined;

onMounted(() => {
    timer = setInterval(() => {
        if (!document.hidden) router.reload({ only: ['unreadNotificationsCount'] });
    }, 60_000);
});
onBeforeUnmount(() => clearInterval(timer));
</script>

<template>
    <DropdownMenu @update:open="loadRecent">
        <DropdownMenuTrigger as-child>
            <Button
                variant="ghost"
                size="icon"
                class="relative size-11"
                :aria-label="count > 0 ? `Notifikasi, ${count} belum dibaca` : 'Notifikasi'"
                data-test="notification-bell"
            >
                <Bell class="size-5" aria-hidden="true" />
                <span
                    v-if="count > 0"
                    class="absolute top-1 right-1 flex min-w-5 items-center justify-center rounded-full bg-destructive px-1 text-[11px] font-bold text-destructive-foreground"
                    data-test="notification-badge"
                    aria-hidden="true"
                >
                    {{ label }}
                </span>
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-[min(22rem,calc(100vw-1.5rem))]">
            <DropdownMenuLabel class="flex items-center justify-between">
                Notifikasi
                <button v-if="count > 0" type="button" class="text-xs font-medium text-primary underline-offset-4 hover:underline" @click="router.post(readAll.url())">
                    Tandai semua dibaca
                </button>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <p v-if="loading" class="p-3 text-sm text-muted-foreground">Memuat…</p>
            <p v-else-if="items.length === 0" class="p-3 text-sm text-muted-foreground">Belum ada notifikasi.</p>
            <DropdownMenuItem v-for="item in items" :key="item.id" class="min-h-11 flex-col items-start gap-0.5" @select="openItem(item)">
                <span class="text-sm" :class="item.read ? 'text-muted-foreground' : 'font-semibold'">{{ item.message }}</span>
                <span class="text-xs text-muted-foreground">{{ formatDateTime(item.created_at) }}</span>
            </DropdownMenuItem>
            <DropdownMenuSeparator />
            <DropdownMenuItem as-child class="min-h-11 justify-center font-medium">
                <a :href="index.url()" @click.prevent="router.visit(index.url())">Lihat semua</a>
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
```

Wayfinder exports `notifications.read-all` as `readAll`; if the generated module uses another name, use the generated export (check `resources/js/routes/notifications/index.ts`).

- [ ] **Step 8: Inbox page** `resources/js/pages/notifications/Index.vue`

```vue
<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { formatDateTime } from '@/lib/format';
import { index, read, readAll } from '@/routes/notifications';

type Item = { id: string; ticket_number: string | null; message: string; read: boolean; created_at: string };

defineProps<{
    notifications: { data: Item[]; links: { url: string | null; label: string; active: boolean }[] };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Notifikasi', href: index() }] },
});
</script>

<template>
    <Head title="Notifikasi" />

    <div class="mx-auto flex w-full max-w-2xl flex-col gap-4 p-4">
        <PageHeader title="Notifikasi" description="Pembaruan tentang laporan Anda.">
            <Button variant="outline" class="h-11" :disabled="notifications.data.every((n) => n.read)" @click="router.post(readAll.url())">
                Tandai semua dibaca
            </Button>
        </PageHeader>

        <EmptyState v-if="notifications.data.length === 0" title="Belum ada notifikasi" description="Pembaruan laporan akan muncul di sini." />

        <ul v-else class="divide-y rounded-xl border">
            <li v-for="item in notifications.data" :key="item.id">
                <button
                    type="button"
                    class="flex min-h-14 w-full items-start gap-3 p-3 text-left hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    @click="router.post(read.url(item.id))"
                >
                    <span v-if="!item.read" class="mt-1.5 size-2.5 shrink-0 rounded-full bg-primary" aria-hidden="true" />
                    <span v-else class="mt-1.5 size-2.5 shrink-0" aria-hidden="true" />
                    <span class="min-w-0">
                        <span class="block text-sm" :class="item.read ? 'text-muted-foreground' : 'font-semibold'">
                            <span v-if="!item.read" class="sr-only">Belum dibaca: </span>{{ item.message }}
                        </span>
                        <span class="block text-xs text-muted-foreground">{{ formatDateTime(item.created_at) }}</span>
                    </span>
                </button>
            </li>
        </ul>

        <Pagination :links="notifications.links" />
    </div>
</template>
```

- [ ] **Step 9: Mount the bell and add nav items.** In `resources/js/components/AppSidebarHeader.vue` import `NotificationBell` and add it as the last child of `<header>` (after the inner `<div class="flex items-center gap-2">`): `<NotificationBell class="ml-auto" />`.

In `useRoleNav.ts` import `Bell` from `@lucide/vue` and `index as notificationsIndex` from `@/routes/notifications`; append `{ title: 'Notifikasi', href: notificationsIndex(), icon: Bell }` to `userNav` and `picNav` (admins use the header bell).

- [ ] **Step 10: Verify**

Run: `php artisan test && php artisan wayfinder:generate && npm run types:check && npm run check && npm run build`
Expected: all pass.

- [ ] **Step 11: Manual check**

Run `composer dev`; create a report as a reporter; as admin the bell shows `1`; open the dropdown, click the item → the report page opens and the badge drops. Tab to the bell, open it with Enter, choose items with the arrow keys.
Expected: all work; the badge has an `aria-label` with the count.

- [ ] **Step 12: Commit**

```bash
git add -A
git commit -m "feat: in-app notification inbox, bell with unread badge and polling"
```

---

### Task 2: Dashboards (user, PIC, admin)

**Files:**
- Create: `app/Support/DashboardStats.php`, `app/Http/Controllers/{User,Pic,Admin}/DashboardController.php`, `resources/js/components/{StatCard,BarList,WeeklyTrend}.vue`, `tests/Feature/Dashboard/{UserDashboardTest,PicDashboardTest,AdminDashboardTest}.php`
- Modify: `routes/web.php`, `resources/js/pages/{user,pic,admin}/Dashboard.vue`

**Interfaces:**
- Produces:
  - `DashboardStats::forUser(User): array` → `{waiting, processing, done, ended, recent[]}`.
  - `DashboardStats::forPic(User): array` → `{new, in_progress, on_hold, overdue, done_this_month, upcoming[]}`.
  - `DashboardStats::forAdmin(CarbonInterface $from, CarbonInterface $to): array` → `{counts{status=>n}, total, overdue, waiting_validation, by_category[{id,name,total}], by_building[{id,name,total}], trend[12×{start,label,created,closed}], avg_completion_hours|null, avg_validation_hours|null, pic_load[{id,name,active_tasks}]}`.
  - Page props: user/pic → `stats`; admin → `stats` and `range {from,to}` (`Y-m-d`).

- [ ] **Step 1: Write the failing tests.** `tests/Feature/Dashboard/UserDashboardTest.php`

```php
<?php

use App\Models\Report;
use App\Models\User;

test('the reporter dashboard summarises only their own reports', function () {
    $me = User::factory()->create();

    Report::factory()->count(2)->for($me)->create();
    Report::factory()->for($me)->inProgress()->create();
    Report::factory()->for($me)->assigned()->create();
    Report::factory()->for($me)->onHold()->create();
    Report::factory()->count(3)->for($me)->closed()->create();
    Report::factory()->for($me)->rejected()->create();
    Report::factory()->for($me)->cancelled()->create();
    Report::factory()->count(4)->create(); // other people's reports

    $this->actingAs($me)->get(route('user.dashboard'))
        ->assertInertia(fn ($page) => $page
            ->component('user/Dashboard')
            ->where('stats.waiting', 2)
            ->where('stats.processing', 3)
            ->where('stats.done', 3)
            ->where('stats.ended', 2)
            ->has('stats.recent', 5));
});

test('a new reporter sees zeros and an empty recent list', function () {
    $this->actingAs(User::factory()->create())->get(route('user.dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('stats.waiting', 0)->where('stats.processing', 0)->where('stats.done', 0)->where('stats.ended', 0)
            ->has('stats.recent', 0));
});

test('recent reports are newest first', function () {
    $me = User::factory()->create();
    $old = Report::factory()->for($me)->create(['created_at' => now()->subDays(3)]);
    $new = Report::factory()->for($me)->create(['created_at' => now()]);

    $this->actingAs($me)->get(route('user.dashboard'))
        ->assertInertia(fn ($page) => $page->where('stats.recent.0.id', $new->id)->where('stats.recent.1.id', $old->id));
});
```

`tests/Feature/Dashboard/PicDashboardTest.php`

```php
<?php

use App\Models\Report;
use App\Models\User;

test('the pic dashboard counts tasks, overdue and this month’s completions', function () {
    $pic = User::factory()->pic()->create();

    $overdue = Report::factory()->assigned($pic)->create(['sla_date' => now()->subDay()]);
    Report::factory()->assigned($pic)->create(['sla_date' => now()->addDays(2)]);
    Report::factory()->inProgress($pic)->create(['sla_date' => now()->addDays(3)]);
    Report::factory()->onHold($pic)->create(['sla_date' => now()->addDays(4)]);
    Report::factory()->count(2)->closed($pic)->create(['closed_at' => now()]);
    Report::factory()->closed($pic)->create(['closed_at' => now()->subMonthNoOverflow()->startOfMonth()]);
    Report::factory()->count(5)->assigned()->create(); // someone else's

    $this->actingAs($pic)->get(route('pic.dashboard'))
        ->assertInertia(fn ($page) => $page
            ->component('pic/Dashboard')
            ->where('stats.new', 2)
            ->where('stats.in_progress', 1)
            ->where('stats.on_hold', 1)
            ->where('stats.overdue', 1)
            ->where('stats.done_this_month', 2)
            ->has('stats.upcoming', 4)
            ->where('stats.upcoming.0.id', $overdue->id));
});

test('a pic without tasks sees zeros', function () {
    $this->actingAs(User::factory()->pic()->create())->get(route('pic.dashboard'))
        ->assertInertia(fn ($page) => $page->where('stats.new', 0)->where('stats.overdue', 0)->has('stats.upcoming', 0));
});
```

`tests/Feature/Dashboard/AdminDashboardTest.php`

```php
<?php

use App\Models\Building;
use App\Models\Category;
use App\Models\Report;
use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->travelTo(Carbon::parse('2026-10-14 12:00', 'Asia/Jakarta')); // a Wednesday
});

test('counts per status, waiting validation and overdue', function () {
    Report::factory()->count(2)->create();
    Report::factory()->assigned()->create(['sla_date' => now()->subDay()]);
    Report::factory()->closed()->create();
    Report::factory()->rejected()->create();

    $this->actingAs($this->admin)->get(route('admin.dashboard'))
        ->assertInertia(fn ($page) => $page
            ->component('admin/Dashboard')
            ->where('stats.counts.Pending', 2)
            ->where('stats.counts.Assigned', 1)
            ->where('stats.counts.Closed', 1)
            ->where('stats.counts.Rejected', 1)
            ->where('stats.counts.Cancelled', 0)
            ->where('stats.total', 5)
            ->where('stats.waiting_validation', 2)
            ->where('stats.overdue', 1)
            ->where('range.from', '2026-09-15')
            ->where('range.to', '2026-10-14'));
});

test('the average completion time is the mean of closed reports', function () {
    $now = now();

    foreach ([1, 2, 3] as $days) {
        Report::factory()->closed()->create([
            'created_at' => $now->copy()->subDays($days),
            'validated_at' => $now->copy()->subDays($days)->addHours(6),
            'closed_at' => $now,
        ]);
    }

    $this->actingAs($this->admin)->get(route('admin.dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('stats.avg_completion_hours', 48.0)
            ->where('stats.avg_validation_hours', 6.0));
});

test('averages are null when there is nothing to average', function () {
    Report::factory()->create();

    $this->actingAs($this->admin)->get(route('admin.dashboard'))
        ->assertInertia(fn ($page) => $page->where('stats.avg_completion_hours', null)->where('stats.avg_validation_hours', null));
});

test('an empty database shows zeros, empty lists and a 12-week trend', function () {
    $this->actingAs($this->admin)->get(route('admin.dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('stats.total', 0)
            ->where('stats.overdue', 0)
            ->has('stats.by_category', 0)
            ->has('stats.by_building', 0)
            ->has('stats.trend', 12)
            ->where('stats.trend.11.created', 0));
});

test('the date filter narrows every count and includes the whole last day', function () {
    Report::factory()->create(['created_at' => '2026-10-10 09:00:00']);
    Report::factory()->create(['created_at' => '2026-10-14 23:30:00']);   // last minute of the range
    Report::factory()->create(['created_at' => '2026-10-05 09:00:00']);   // outside a 7-day window
    Report::factory()->create(['created_at' => '2026-10-15 00:30:00']);   // the day after

    $this->actingAs($this->admin)->get(route('admin.dashboard', ['from' => '2026-10-08', 'to' => '2026-10-14']))
        ->assertInertia(fn ($page) => $page->where('stats.total', 2)->where('range.from', '2026-10-08'));
});

test('an invalid range is a validation error', function () {
    $this->actingAs($this->admin)->get(route('admin.dashboard', ['from' => '2026-10-14', 'to' => '2026-10-01']))
        ->assertSessionHasErrors('to');
});

test('reports are grouped by category and building with ids for drill-down', function () {
    $category = Category::factory()->create(['name' => 'Elektronik']);
    $building = Building::factory()->create(['name' => 'Gedung A']);
    $room = Room::factory()->for($building)->create();

    Report::factory()->count(3)->create(['category_id' => $category->id, 'room_id' => $room->id]);
    Report::factory()->create();

    $this->actingAs($this->admin)->get(route('admin.dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('stats.by_category.0.id', $category->id)
            ->where('stats.by_category.0.name', 'Elektronik')
            ->where('stats.by_category.0.total', 3)
            ->where('stats.by_building.0.id', $building->id)
            ->where('stats.by_building.0.total', 3));
});

test('the weekly trend counts reports created and closed per ISO week', function () {
    Report::factory()->create(['created_at' => '2026-10-07 10:00:00']);                       // week of 5 Oct
    Report::factory()->closed()->create(['created_at' => '2026-10-13 10:00:00', 'closed_at' => '2026-10-13 15:00:00']); // week of 12 Oct

    $this->actingAs($this->admin)->get(route('admin.dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('stats.trend.10.start', '2026-10-05')
            ->where('stats.trend.10.created', 1)
            ->where('stats.trend.11.start', '2026-10-12')
            ->where('stats.trend.11.created', 1)
            ->where('stats.trend.11.closed', 1));
});

test('pic load lists active tasks per active pic', function () {
    $busy = User::factory()->pic()->create(['name' => 'Pak Sibuk']);
    User::factory()->pic()->inactive()->create();
    Report::factory()->count(2)->inProgress($busy)->create();
    Report::factory()->closed($busy)->create();

    $this->actingAs($this->admin)->get(route('admin.dashboard'))
        ->assertInertia(fn ($page) => $page
            ->has('stats.pic_load', 1)
            ->where('stats.pic_load.0.name', 'Pak Sibuk')
            ->where('stats.pic_load.0.active_tasks', 2));
});

test('statistics are cached for five minutes', function () {
    Report::factory()->create();
    $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertInertia(fn ($page) => $page->where('stats.total', 1));

    Report::factory()->create();
    $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertInertia(fn ($page) => $page->where('stats.total', 1));

    $this->travelTo(now()->addMinutes(6));
    $this->actingAs($this->admin)->get(route('admin.dashboard', ['to' => now()->toDateString()]))->assertInertia(fn ($page) => $page->where('stats.total', 2));
});

test('only admins see admin statistics', function (string $state) {
    $this->actingAs(User::factory()->{$state}()->create())->get(route('admin.dashboard'))->assertForbidden();
})->with(['reporter', 'pic']);
```

- [ ] **Step 2: Run to verify they fail**

Run: `php artisan test tests/Feature/Dashboard`
Expected: FAIL (the placeholder pages from Plan 1 have no `stats` prop).

- [ ] **Step 3: Create `app/Support/DashboardStats.php`**

```php
<?php

namespace App\Support;

use App\Enums\ReportStatus;
use App\Enums\UserRole;
use App\Models\Report;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class DashboardStats
{
    /** @return array<string, mixed> */
    public function forUser(User $user): array
    {
        $counts = Report::query()->where('user_id', $user->id)
            ->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        $sum = fn (ReportStatus ...$statuses) => (int) collect($statuses)->sum(fn (ReportStatus $status) => (int) ($counts[$status->value] ?? 0));

        return [
            'waiting' => $sum(ReportStatus::Pending),
            'processing' => $sum(ReportStatus::Assigned, ReportStatus::InProgress, ReportStatus::OnHold),
            'done' => $sum(ReportStatus::Closed),
            'ended' => $sum(ReportStatus::Rejected, ReportStatus::Cancelled),
            'recent' => $this->summaries(
                Report::query()->visibleTo($user)->latest('created_at')->latest('id')->limit(5),
            ),
        ];
    }

    /** @return array<string, mixed> */
    public function forPic(User $pic): array
    {
        $mine = fn () => Report::query()->visibleTo($pic);
        $active = [ReportStatus::Assigned, ReportStatus::InProgress, ReportStatus::OnHold];

        return [
            'new' => $mine()->where('status', ReportStatus::Assigned)->count(),
            'in_progress' => $mine()->where('status', ReportStatus::InProgress)->count(),
            'on_hold' => $mine()->where('status', ReportStatus::OnHold)->count(),
            'overdue' => $mine()->overdue()->count(),
            'done_this_month' => $mine()->where('status', ReportStatus::Closed)
                ->whereBetween('closed_at', [now()->startOfMonth(), now()->endOfMonth()])->count(),
            'upcoming' => $this->summaries($mine()->whereIn('status', $active)->orderBy('sla_date')->limit(5)),
        ];
    }

    /** @return array<string, mixed> */
    public function forAdmin(CarbonInterface $from, CarbonInterface $to): array
    {
        $range = fn () => Report::query()->whereBetween('reports.created_at', [$from->startOfDay(), $to->endOfDay()]);

        $byStatus = $range()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $counts = [];
        foreach (ReportStatus::cases() as $status) {
            $counts[$status->value] = (int) ($byStatus[$status->value] ?? 0);
        }

        $avgHours = fn (string $column, ?ReportStatus $status = null) => $this->averageHours(
            $range()->when($status, fn (Builder $query) => $query->where('status', $status))->whereNotNull($column),
            $column,
        );

        return [
            'counts' => $counts,
            'total' => array_sum($counts),
            'overdue' => Report::overdue()->count(),
            'waiting_validation' => $counts[ReportStatus::Pending->value],
            'by_category' => $range()
                ->join('categories', 'categories.id', '=', 'reports.category_id')
                ->selectRaw('categories.id, categories.name, COUNT(*) as total')
                ->groupBy('categories.id', 'categories.name')->orderByDesc('total')->limit(10)->get()
                ->map(fn ($row) => ['id' => (int) $row->id, 'name' => $row->name, 'total' => (int) $row->total])->all(),
            'by_building' => $range()
                ->join('rooms', 'rooms.id', '=', 'reports.room_id')
                ->join('buildings', 'buildings.id', '=', 'rooms.building_id')
                ->selectRaw('buildings.id, buildings.name, COUNT(*) as total')
                ->groupBy('buildings.id', 'buildings.name')->orderByDesc('total')->limit(10)->get()
                ->map(fn ($row) => ['id' => (int) $row->id, 'name' => $row->name, 'total' => (int) $row->total])->all(),
            'trend' => $this->weeklyTrend(),
            'avg_completion_hours' => $avgHours('closed_at', ReportStatus::Closed),
            'avg_validation_hours' => $avgHours('validated_at'),
            'pic_load' => User::query()
                ->where('role', UserRole::Pic)->where('is_active', true)
                ->withCount(['assignedReports as active_tasks' => fn ($query) => $query->whereNotIn('status', ReportStatus::finalValues())])
                ->orderByDesc('active_tasks')->orderBy('name')->get(['id', 'name'])
                ->map(fn (User $pic) => ['id' => $pic->id, 'name' => $pic->name, 'active_tasks' => (int) $pic->active_tasks])->all(),
        ];
    }

    /** @param Builder<Report> $query */
    private function averageHours(Builder $query, string $endColumn): ?float
    {
        $seconds = $query->avg(DB::raw("TIMESTAMPDIFF(SECOND, reports.created_at, reports.{$endColumn})"));

        return $seconds === null ? null : round($seconds / 3600, 1);
    }

    /** @return list<array{start: string, label: string, created: int, closed: int}> */
    private function weeklyTrend(): array
    {
        $first = now()->startOfWeek()->subWeeks(11);
        $key = fn (CarbonInterface $date) => $date->isoWeekYear() * 100 + $date->isoWeek();

        $created = Report::query()->where('created_at', '>=', $first)->pluck('created_at')
            ->countBy(fn ($date) => $key($date));
        $closed = Report::query()->whereNotNull('closed_at')->where('closed_at', '>=', $first)->pluck('closed_at')
            ->countBy(fn ($date) => $key($date));

        $weeks = [];
        for ($i = 0; $i < 12; $i++) {
            $start = $first->addWeeks($i);
            $weeks[] = [
                'start' => $start->toDateString(),
                'label' => $start->format('d/m'),
                'created' => (int) ($created[$key($start)] ?? 0),
                'closed' => (int) ($closed[$key($start)] ?? 0),
            ];
        }

        return $weeks;
    }

    /**
     * @param  Builder<Report>  $query
     * @return list<array<string, mixed>>
     */
    private function summaries(Builder $query): array
    {
        return $query->with(['category:id,name', 'room.building:id,name', 'user:id,name', 'assignedPic:id,name'])
            ->get()->map(fn (Report $report) => ReportPresenter::summary($report))->all();
    }
}
```

- [ ] **Step 4: Controllers.** `app/Http/Controllers/User/DashboardController.php`

```php
<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Support\DashboardStats;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardStats $stats): Response
    {
        return Inertia::render('user/Dashboard', ['stats' => $stats->forUser($request->user())]);
    }
}
```

`app/Http/Controllers/Pic/DashboardController.php` — identical, namespace `App\Http\Controllers\Pic`, render `'pic/Dashboard'`, call `$stats->forPic($request->user())`.

`app/Http/Controllers/Admin/DashboardController.php`

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\DashboardStats;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardStats $stats): Response
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $to = Date::parse($data['to'] ?? now()->toDateString())->startOfDay();
        $from = Date::parse($data['from'] ?? $to->subDays(29)->toDateString())->startOfDay();

        $key = "dashboard.admin.{$from->toDateString()}.{$to->toDateString()}";

        return Inertia::render('admin/Dashboard', [
            'stats' => Cache::remember($key, now()->addMinutes(5), fn () => $stats->forAdmin($from, $to)),
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
        ]);
    }
}
```

- [ ] **Step 5: Routes.** In `routes/web.php` replace the three `Route::inertia('dashboard', …)` lines:

```php
// user group
        Route::get('dashboard', UserDashboardController::class)->name('dashboard');
// admin group
        Route::get('dashboard', AdminDashboardController::class)->name('dashboard');
// pic group
        Route::get('dashboard', PicDashboardController::class)->name('dashboard');
```

and add `use App\Http\Controllers\User\DashboardController as UserDashboardController; use App\Http\Controllers\Admin\DashboardController as AdminDashboardController; use App\Http\Controllers\Pic\DashboardController as PicDashboardController;`.

- [ ] **Step 6: Run the tests**

Run: `php artisan test tests/Feature/Dashboard`
Expected: all pass. The cache test relies on `CACHE_STORE=array` from `phpunit.xml`. If the first two weekly-trend assertions fail by one week, check that `now()` is the Wednesday `2026-10-14` set in `beforeEach` (ISO weeks start on Monday, so the current week starts `2026-10-12`).

- [ ] **Step 7: UI components.** `resources/js/components/StatCard.vue`

```vue
<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    label: string;
    value: number | string;
    href?: string;
    tone?: 'default' | 'warning' | 'danger' | 'success';
    hint?: string;
}>();

const tone = computed(
    () =>
        ({
            default: 'text-foreground',
            warning: 'text-status-on-hold',
            danger: 'text-status-rejected',
            success: 'text-status-closed',
        })[props.tone ?? 'default'],
);
</script>

<template>
    <component
        :is="href ? Link : 'div'"
        :href="href"
        class="flex min-h-24 flex-col justify-between rounded-xl border bg-card p-4"
        :class="href ? 'hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none' : ''"
    >
        <span class="text-sm text-muted-foreground">{{ label }}</span>
        <span class="text-3xl font-bold tabular-nums" :class="tone">{{ value }}</span>
        <span v-if="hint" class="text-xs text-muted-foreground">{{ hint }}</span>
    </component>
</template>
```

`resources/js/components/BarList.vue`

```vue
<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    items: { label: string; value: number; href?: string }[];
    empty?: string;
}>();

const max = computed(() => Math.max(1, ...props.items.map((item) => item.value)));
</script>

<template>
    <p v-if="items.length === 0" class="text-sm text-muted-foreground">{{ empty ?? 'Belum ada data.' }}</p>

    <ul v-else class="grid gap-1">
        <li v-for="item in items" :key="item.label">
            <component
                :is="item.href ? Link : 'div'"
                :href="item.href"
                class="grid gap-1 rounded-md p-1.5"
                :class="item.href ? 'hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none' : ''"
            >
                <span class="flex justify-between gap-2 text-sm">
                    <span class="truncate">{{ item.label }}</span>
                    <span class="font-semibold tabular-nums">{{ item.value }}</span>
                </span>
                <span class="h-2 overflow-hidden rounded-full bg-muted" aria-hidden="true">
                    <span class="block h-full rounded-full bg-primary" :style="{ width: `${(item.value / max) * 100}%` }" />
                </span>
            </component>
        </li>
    </ul>
</template>
```

`resources/js/components/WeeklyTrend.vue`

```vue
<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    weeks: { start: string; label: string; created: number; closed: number }[];
}>();

const max = computed(() => Math.max(1, ...props.weeks.flatMap((week) => [week.created, week.closed])));
const height = (value: number) => `${Math.max((value / max.value) * 100, value > 0 ? 4 : 0)}%`;
</script>

<template>
    <div>
        <div class="mb-2 flex gap-4 text-xs text-muted-foreground">
            <span class="flex items-center gap-1.5"><span class="size-3 rounded-sm bg-primary" aria-hidden="true" /> Laporan masuk</span>
            <span class="flex items-center gap-1.5"><span class="size-3 rounded-sm bg-brand-accent-strong" aria-hidden="true" /> Laporan selesai</span>
        </div>

        <div class="flex h-40 items-end gap-1" aria-hidden="true">
            <div v-for="week in weeks" :key="week.start" class="flex h-full flex-1 flex-col justify-end gap-1">
                <div class="flex flex-1 items-end justify-center gap-0.5">
                    <div class="w-1/2 rounded-t bg-primary" :style="{ height: height(week.created) }" />
                    <div class="w-1/2 rounded-t bg-brand-accent-strong" :style="{ height: height(week.closed) }" />
                </div>
                <span class="text-center text-[10px] text-muted-foreground">{{ week.label }}</span>
            </div>
        </div>

        <table class="sr-only">
            <caption>Laporan masuk dan selesai per minggu</caption>
            <thead><tr><th scope="col">Minggu mulai</th><th scope="col">Masuk</th><th scope="col">Selesai</th></tr></thead>
            <tbody>
                <tr v-for="week in weeks" :key="week.start"><td>{{ week.start }}</td><td>{{ week.created }}</td><td>{{ week.closed }}</td></tr>
            </tbody>
        </table>
    </div>
</template>
```

- [ ] **Step 8: Dashboard pages.** `resources/js/pages/user/Dashboard.vue`

```vue
<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatCard from '@/components/StatCard.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/lib/format';
import { create, index, show } from '@/routes/reports';
import { dashboard } from '@/routes/user';

defineProps<{
    stats: {
        waiting: number;
        processing: number;
        done: number;
        ended: number;
        recent: { id: number; ticket_number: string; title: string; status: string; created_at: string }[];
    };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Beranda', href: dashboard() }] },
});
</script>

<template>
    <Head title="Beranda" />

    <div class="flex flex-col gap-6 p-4">
        <PageHeader title="Beranda" description="Ringkasan laporan Anda.">
            <Button as-child class="h-11"><Link :href="create()"><Plus class="size-4" /> Laporkan kerusakan</Link></Button>
        </PageHeader>

        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <StatCard label="Menunggu validasi" :value="stats.waiting" :href="index.url({ query: { status: 'Pending' } })" />
            <StatCard label="Sedang diproses" :value="stats.processing" tone="warning" :href="index.url()" />
            <StatCard label="Selesai" :value="stats.done" tone="success" :href="index.url({ query: { status: 'Closed' } })" />
            <StatCard label="Ditolak / dibatalkan" :value="stats.ended" :href="index.url()" />
        </div>

        <section aria-labelledby="recent">
            <h2 id="recent" class="mb-2 font-semibold">Laporan terbaru</h2>
            <EmptyState v-if="stats.recent.length === 0" title="Belum ada laporan" description="Temukan alat rusak? Laporkan lewat tombol di atas." />
            <ul v-else class="divide-y rounded-xl border">
                <li v-for="report in stats.recent" :key="report.id">
                    <Link :href="show.url(report.id)" class="flex items-center justify-between gap-3 p-3 hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none">
                        <span class="min-w-0">
                            <span class="block truncate font-medium">{{ report.title }}</span>
                            <span class="block text-sm text-muted-foreground">{{ report.ticket_number }} · {{ formatDate(report.created_at) }}</span>
                        </span>
                        <StatusBadge :status="report.status" />
                    </Link>
                </li>
            </ul>
        </section>
    </div>
</template>
```

`resources/js/pages/pic/Dashboard.vue`

```vue
<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatCard from '@/components/StatCard.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { formatDateTime } from '@/lib/format';
import { dashboard } from '@/routes/pic';
import { index, show } from '@/routes/pic/tasks';

defineProps<{
    stats: {
        new: number;
        in_progress: number;
        on_hold: number;
        overdue: number;
        done_this_month: number;
        upcoming: { id: number; ticket_number: string; title: string; status: string; is_overdue: boolean; sla_date: string | null }[];
    };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Beranda', href: dashboard() }] },
});
</script>

<template>
    <Head title="Beranda" />

    <div class="flex flex-col gap-6 p-4">
        <PageHeader title="Beranda" description="Ringkasan tugas Anda." />

        <div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
            <StatCard label="Tugas baru" :value="stats.new" :href="index.url()" />
            <StatCard label="Dikerjakan" :value="stats.in_progress" :href="index.url()" />
            <StatCard label="Ditunda" :value="stats.on_hold" tone="warning" :href="index.url()" />
            <StatCard label="Terlambat" :value="stats.overdue" tone="danger" :href="index.url()" />
            <StatCard label="Selesai bulan ini" :value="stats.done_this_month" tone="success" :href="index.url({ query: { tab: 'done' } })" />
        </div>

        <section aria-labelledby="upcoming">
            <h2 id="upcoming" class="mb-2 font-semibold">SLA terdekat</h2>
            <EmptyState v-if="stats.upcoming.length === 0" title="Tidak ada tugas aktif" description="Tugas baru dari admin akan muncul di sini." />
            <ul v-else class="divide-y rounded-xl border">
                <li v-for="task in stats.upcoming" :key="task.id">
                    <Link :href="show.url(task.id)" class="flex items-center justify-between gap-3 p-3 hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none">
                        <span class="min-w-0">
                            <span class="block truncate font-medium">{{ task.title }}</span>
                            <span class="block text-sm" :class="task.is_overdue ? 'font-semibold text-status-rejected' : 'text-muted-foreground'">
                                {{ task.ticket_number }} · SLA {{ task.sla_date ? formatDateTime(task.sla_date) : '-' }}{{ task.is_overdue ? ' (terlambat)' : '' }}
                            </span>
                        </span>
                        <StatusBadge :status="task.status" />
                    </Link>
                </li>
            </ul>
        </section>
    </div>
</template>
```

`resources/js/pages/admin/Dashboard.vue`

```vue
<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import BarList from '@/components/BarList.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatCard from '@/components/StatCard.vue';
import WeeklyTrend from '@/components/WeeklyTrend.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes/admin';
import { index as reportsIndex } from '@/routes/admin/reports';

const props = defineProps<{
    stats: {
        counts: Record<string, number>;
        total: number;
        overdue: number;
        waiting_validation: number;
        by_category: { id: number; name: string; total: number }[];
        by_building: { id: number; name: string; total: number }[];
        trend: { start: string; label: string; created: number; closed: number }[];
        avg_completion_hours: number | null;
        avg_validation_hours: number | null;
        pic_load: { id: number; name: string; active_tasks: number }[];
    };
    range: { from: string; to: string };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Beranda', href: dashboard() }] },
});

const form = reactive({ from: props.range.from, to: props.range.to });

function apply() {
    router.get(dashboard.url(), { from: form.from, to: form.to }, { preserveState: true });
}

function lastDays(days: number) {
    const to = new Date();
    const from = new Date(to.getTime() - (days - 1) * 86_400_000);
    const fmt = (d: Date) => new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Jakarta' }).format(d);
    form.from = fmt(from);
    form.to = fmt(to);
    apply();
}

const link = (query: Record<string, string | number>) => reportsIndex.url({ query: { from: props.range.from, to: props.range.to, ...query } });

const duration = (hours: number | null) => {
    if (hours === null) return '-';
    return hours >= 48 ? `${(hours / 24).toFixed(1)} hari` : `${hours} jam`;
};

const statusCards: { key: string; label: string; tone?: 'warning' | 'danger' | 'success' }[] = [
    { key: 'Pending', label: 'Menunggu validasi', tone: 'warning' },
    { key: 'Assigned', label: 'Ditugaskan' },
    { key: 'In_Progress', label: 'Dikerjakan' },
    { key: 'On_Hold', label: 'Ditunda', tone: 'warning' },
    { key: 'Closed', label: 'Selesai', tone: 'success' },
    { key: 'Rejected', label: 'Ditolak' },
    { key: 'Cancelled', label: 'Dibatalkan' },
];
</script>

<template>
    <Head title="Beranda" />

    <div class="flex flex-col gap-6 p-4">
        <PageHeader title="Beranda" description="Ringkasan seluruh laporan pada rentang tanggal yang dipilih." />

        <form class="flex flex-wrap items-end gap-3 rounded-xl border p-4" @submit.prevent="apply">
            <div class="grid gap-1">
                <Label for="d-from">Dari</Label>
                <Input id="d-from" v-model="form.from" type="date" class="h-11" required />
            </div>
            <div class="grid gap-1">
                <Label for="d-to">Sampai</Label>
                <Input id="d-to" v-model="form.to" type="date" class="h-11" required />
            </div>
            <Button type="submit" class="h-11">Terapkan</Button>
            <Button type="button" variant="outline" class="h-11" @click="lastDays(7)">7 hari</Button>
            <Button type="button" variant="outline" class="h-11" @click="lastDays(30)">30 hari</Button>
            <Button type="button" variant="outline" class="h-11" @click="lastDays(90)">90 hari</Button>
        </form>

        <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
            <StatCard v-for="card in statusCards" :key="card.key" :label="card.label" :value="stats.counts[card.key] ?? 0" :tone="card.tone" :href="link({ status: card.key })" />
            <StatCard label="Terlambat (saat ini)" :value="stats.overdue" tone="danger" :href="reportsIndex.url({ query: { overdue: 1 } })" />
        </div>

        <div class="grid gap-3 sm:grid-cols-2">
            <StatCard label="Rata-rata waktu validasi" :value="duration(stats.avg_validation_hours)" hint="Sejak dilaporkan sampai divalidasi" />
            <StatCard label="Rata-rata waktu penyelesaian" :value="duration(stats.avg_completion_hours)" hint="Sejak dilaporkan sampai selesai" />
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="rounded-xl border p-4" aria-labelledby="by-category">
                <h2 id="by-category" class="mb-2 font-semibold">Laporan per kategori</h2>
                <BarList :items="stats.by_category.map((c) => ({ label: c.name, value: c.total, href: link({ category_id: c.id }) }))" empty="Belum ada laporan pada rentang ini." />
            </section>
            <section class="rounded-xl border p-4" aria-labelledby="by-building">
                <h2 id="by-building" class="mb-2 font-semibold">Laporan per gedung</h2>
                <BarList :items="stats.by_building.map((b) => ({ label: b.name, value: b.total, href: link({ building_id: b.id }) }))" empty="Belum ada laporan pada rentang ini." />
            </section>
        </div>

        <section class="rounded-xl border p-4" aria-labelledby="trend">
            <h2 id="trend" class="mb-2 font-semibold">Tren 12 minggu terakhir</h2>
            <WeeklyTrend :weeks="stats.trend" />
        </section>

        <section class="rounded-xl border p-4" aria-labelledby="load">
            <h2 id="load" class="mb-2 font-semibold">Beban PIC (tugas aktif)</h2>
            <BarList :items="stats.pic_load.map((p) => ({ label: p.name, value: p.active_tasks, href: reportsIndex.url({ query: { pic_id: p.id } }) }))" empty="Belum ada PIC aktif." />
        </section>
    </div>
</template>
```

- [ ] **Step 9: Verify**

Run: `php artisan test && composer lint:check && composer types:check && php artisan wayfinder:generate && npm run types:check && npm run check && npm run build`
Expected: all pass.

- [ ] **Step 10: Commit**

```bash
git add -A
git commit -m "feat: dashboards for reporters, PICs and admins with cached statistics"
```

---

### Task 3: Export to Excel and PDF

**Files:**
- Create: `app/Support/ReportExport.php`, `app/Http/Controllers/Admin/ExportController.php`, `resources/views/exports/reports.blade.php`, `tests/Feature/Admin/{ExportExcelTest,ExportPdfTest}.php`
- Modify: `routes/web.php`, `resources/js/pages/admin/reports/Index.vue`

**Interfaces:**
- Produces:
  - `ReportExport::HEADERS` (19 columns), `::row(Report): array`, `::sanitize(mixed): mixed`, `::filterText(array $filterValues): string`.
  - Routes `GET admin/reports/export/excel` (`admin.reports.export.excel`) and `GET admin/reports/export/pdf` (`admin.reports.export.pdf`); both accept the `ReportFilter` query string.

- [ ] **Step 1: Install dompdf**

Run: `composer require barryvdh/laravel-dompdf`
Expected: package installed. If Composer reports a missing PHP extension (`ext-dom`), enable it in `C:\php-8.5.5\php.ini` and retry.

- [ ] **Step 2: Write the failing tests.** `tests/Feature/Admin/ExportExcelTest.php`

```php
<?php

use App\Models\Category;
use App\Models\Report;
use App\Models\User;
use App\Support\Spreadsheet\RowReader;

beforeEach(fn () => $this->admin = User::factory()->admin()->create());

/** @return list<list<mixed>> rows of the downloaded workbook (header first) */
function workbookRows($response): array
{
    $path = tempnam(sys_get_temp_dir(), 'exp').'.xlsx';
    file_put_contents($path, $response->streamedContent());
    $rows = iterator_to_array(RowReader::rows($path, 'xlsx'), false);
    @unlink($path);

    return $rows;
}

test('the workbook has the agreed columns and one row per matching report', function () {
    $category = Category::factory()->create(['name' => 'Elektronik']);
    $pic = User::factory()->pic()->create(['name' => 'Pak Teknisi']);
    $report = Report::factory()->closed($pic)->create([
        'category_id' => $category->id,
        'title' => 'Proyektor mati',
        'repair_cost' => 350000,
        'vendor_name' => 'CV Teknik Jaya',
        'sla_date' => now()->subDay(),
    ]);
    Report::factory()->count(2)->create();

    $response = $this->actingAs($this->admin)->get(route('admin.reports.export.excel', ['status' => 'Closed', 'category_id' => $category->id]))
        ->assertOk();

    expect($response->headers->get('content-disposition'))->toMatch('/laporan-siperkasa-\d{8}-\d{4}\.xlsx/');

    $rows = workbookRows($response);

    expect($rows[0])->toBe([
        'Tiket', 'Tanggal lapor', 'Pelapor', 'NIM/NIP', 'Judul', 'Kategori', 'Gedung', 'Ruang', 'Kode aset',
        'Urgensi pelapor', 'Prioritas admin', 'Status', 'PIC', 'SLA', 'Terlambat', 'Tanggal selesai',
        'Tindakan perbaikan', 'Biaya', 'Vendor',
    ]);
    expect($rows)->toHaveCount(2);
    expect($rows[1][0])->toBe($report->ticket_number)
        ->and($rows[1][4])->toBe('Proyektor mati')
        ->and($rows[1][5])->toBe('Elektronik')
        ->and($rows[1][11])->toBe('Selesai')
        ->and($rows[1][12])->toBe('Pak Teknisi')
        ->and($rows[1][14])->toBe('Tidak')           // closed reports are never overdue
        ->and((float) $rows[1][17])->toBe(350000.0)
        ->and($rows[1][18])->toBe('CV Teknik Jaya');
});

test('the export applies the same filters as the list', function () {
    Report::factory()->count(3)->create();
    Report::factory()->assigned()->create(['sla_date' => now()->subDay()]);

    $rows = workbookRows($this->actingAs($this->admin)->get(route('admin.reports.export.excel', ['overdue' => 1])));

    expect($rows)->toHaveCount(2)->and($rows[1][14])->toBe('Ya');
});

test('every matching report is exported, not just one page', function () {
    Report::factory()->count(20)->create();

    expect(workbookRows($this->actingAs($this->admin)->get(route('admin.reports.export.excel'))))->toHaveCount(21);
});

test('a filter that matches nothing still yields a valid workbook', function () {
    Report::factory()->create();

    $rows = workbookRows($this->actingAs($this->admin)->get(route('admin.reports.export.excel', ['status' => 'Cancelled'])));

    expect($rows)->toHaveCount(2)
        ->and($rows[0][0])->toBe('Tiket')
        ->and($rows[1][0])->toBe('Tidak ada data');
});

test('values that look like formulas are exported as text', function () {
    Report::factory()->create(['title' => '=HYPERLINK("http://evil.example","klik")']);
    Report::factory()->create(['title' => '+1 AC rusak']);
    Report::factory()->create(['title' => '@SUM(A1)']);

    $titles = collect(workbookRows($this->actingAs($this->admin)->get(route('admin.reports.export.excel'))))->skip(1)->pluck(4)->all();

    foreach ($titles as $title) {
        expect($title)->toStartWith("'");
    }
});

test('invalid filters are validation errors', function () {
    $this->actingAs($this->admin)->get(route('admin.reports.export.excel', ['status' => 'Nope']))->assertSessionHasErrors('status');
});

test('only admins can export', function (string $state) {
    $this->actingAs(User::factory()->{$state}()->create())->get(route('admin.reports.export.excel'))->assertForbidden();
})->with(['reporter', 'pic']);
```

`tests/Feature/Admin/ExportPdfTest.php`

```php
<?php

use App\Models\Report;
use App\Models\User;
use App\Support\ReportExport;

beforeEach(fn () => $this->admin = User::factory()->admin()->create());

test('the pdf is a real PDF download', function () {
    Report::factory()->count(3)->create();

    $response = $this->actingAs($this->admin)->get(route('admin.reports.export.pdf'))->assertOk();

    expect($response->headers->get('content-type'))->toBe('application/pdf')
        ->and($response->headers->get('content-disposition'))->toMatch('/laporan-siperkasa-\d{8}-\d{4}\.pdf/')
        ->and(str_starts_with($response->getContent(), '%PDF'))->toBeTrue();
});

test('the pdf view carries the header, filter summary, status counts and rows', function () {
    $closed = Report::factory()->closed()->create(['title' => 'AC bocor']);
    Report::factory()->create();

    $html = view('exports.reports', [
        'logo' => 'data:image/png;base64,AAAA',
        'printedAt' => '15 Okt 2026 10:00',
        'filterText' => 'Status: Selesai',
        'summary' => ['Menunggu' => 1, 'Selesai' => 1],
        'rows' => [['Tiket' => $closed->ticket_number, 'Tanggal' => '15 Okt 2026', 'Judul' => 'AC bocor', 'Lokasi' => 'Gedung A, 101', 'Status' => 'Selesai', 'PIC' => 'Pak Teknisi', 'SLA' => '-']],
    ])->render();

    expect($html)->toContain('Laporan Kerusakan Aset')
        ->toContain('UNIVERSITAS ISLAM NEGERI SIBER SYEKH NURJATI CIREBON')
        ->toContain('Status: Selesai')
        ->toContain('Menunggu')
        ->toContain($closed->ticket_number)
        ->toContain('AC bocor');
});

test('an empty result still produces a pdf', function () {
    $response = $this->actingAs($this->admin)->get(route('admin.reports.export.pdf'))->assertOk();

    expect(str_starts_with($response->getContent(), '%PDF'))->toBeTrue();
});

test('more rows than the limit are refused with a clear error', function () {
    config(['siperkasa.pdf_max_rows' => 2]);
    Report::factory()->count(3)->create();

    $this->actingAs($this->admin)->from(route('admin.reports.index'))->get(route('admin.reports.export.pdf'))
        ->assertRedirect(route('admin.reports.index'))
        ->assertSessionHasErrors(['export' => 'Terlalu banyak data (3 baris) untuk PDF. Gunakan Excel atau persempit filter.']);
});

test('exactly the limit is allowed', function () {
    config(['siperkasa.pdf_max_rows' => 3]);
    Report::factory()->count(3)->create();

    $this->actingAs($this->admin)->get(route('admin.reports.export.pdf'))->assertOk();
});

test('the filter text names the active filters', function () {
    expect(ReportExport::filterText([]))->toBe('Semua laporan')
        ->and(ReportExport::filterText(['status' => 'Closed', 'from' => '2026-10-01', 'to' => '2026-10-31']))
        ->toBe('Status: Selesai · Dari: 2026-10-01 · Sampai: 2026-10-31');
});

test('only admins can export a pdf', function (string $state) {
    $this->actingAs(User::factory()->{$state}()->create())->get(route('admin.reports.export.pdf'))->assertForbidden();
})->with(['reporter', 'pic']);
```

- [ ] **Step 3: Run to verify they fail**

Run: `php artisan test tests/Feature/Admin/ExportExcelTest.php tests/Feature/Admin/ExportPdfTest.php`
Expected: FAIL (`Route [admin.reports.export.excel] not defined`).

- [ ] **Step 4: Create `app/Support/ReportExport.php`**

```php
<?php

namespace App\Support;

use App\Enums\ReportStatus;
use App\Models\Building;
use App\Models\Category;
use App\Models\Report;
use App\Models\User;

final class ReportExport
{
    public const HEADERS = [
        'Tiket', 'Tanggal lapor', 'Pelapor', 'NIM/NIP', 'Judul', 'Kategori', 'Gedung', 'Ruang', 'Kode aset',
        'Urgensi pelapor', 'Prioritas admin', 'Status', 'PIC', 'SLA', 'Terlambat', 'Tanggal selesai',
        'Tindakan perbaikan', 'Biaya', 'Vendor',
    ];

    /** @return list<mixed> one spreadsheet row, aligned with HEADERS */
    public static function row(Report $report): array
    {
        return array_map(self::sanitize(...), [
            $report->ticket_number,
            $report->created_at?->format('Y-m-d H:i'),
            $report->user?->name,
            $report->user?->nim_nip,
            $report->title,
            $report->category?->name,
            $report->room?->building?->name,
            $report->room?->name,
            $report->asset?->asset_code,
            $report->user_priority->label(),
            $report->admin_priority?->label(),
            $report->status->label(),
            $report->assignedPic?->name,
            $report->sla_date?->format('Y-m-d H:i'),
            $report->isOverdue() ? 'Ya' : 'Tidak',
            $report->completed_on?->toDateString(),
            $report->repair_action,
            $report->repair_cost === null ? null : (float) $report->repair_cost,
            $report->vendor_name,
        ]);
    }

    /** Prevents spreadsheet formula injection: text starting with = + - @ tab or CR is prefixed with a quote. */
    public static function sanitize(mixed $value): mixed
    {
        if (is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }

    /** @param array<string, mixed> $values validated ReportFilter values */
    public static function filterText(array $values): string
    {
        $parts = [];

        if (! empty($values['status'])) {
            $parts[] = 'Status: '.ReportStatus::from($values['status'])->label();
        }
        if (! empty($values['category_id'])) {
            $parts[] = 'Kategori: '.(Category::find($values['category_id'])?->name ?? $values['category_id']);
        }
        if (! empty($values['building_id'])) {
            $parts[] = 'Gedung: '.(Building::find($values['building_id'])?->name ?? $values['building_id']);
        }
        if (! empty($values['pic_id'])) {
            $parts[] = 'PIC: '.(User::find($values['pic_id'])?->name ?? $values['pic_id']);
        }
        if (! empty($values['priority'])) {
            $parts[] = 'Prioritas: '.$values['priority'];
        }
        if (! empty($values['from'])) {
            $parts[] = 'Dari: '.$values['from'];
        }
        if (! empty($values['to'])) {
            $parts[] = 'Sampai: '.$values['to'];
        }
        if (! empty($values['overdue'])) {
            $parts[] = 'Hanya terlambat';
        }
        if (! empty($values['q'])) {
            $parts[] = 'Cari: '.$values['q'];
        }

        return $parts === [] ? 'Semua laporan' : implode(' · ', $parts);
    }
}
```

- [ ] **Step 5: Create `app/Http/Controllers/Admin/ExportController.php`**

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ReportStatus;
use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Support\ReportExport;
use App\Support\ReportFilter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function excel(Request $request): StreamedResponse
    {
        Gate::authorize('viewAny', Report::class);

        $filter = ReportFilter::fromRequest($request);
        $name = 'laporan-siperkasa-'.now()->format('Ymd-Hi').'.xlsx';

        return response()->streamDownload(function () use ($filter) {
            $writer = new Writer;
            $writer->openToFile('php://output');
            $writer->addRow(Row::fromValues(ReportExport::HEADERS));

            $count = 0;
            $reports = $filter->apply(Report::query())
                ->with(['user:id,name,nim_nip', 'category:id,name', 'room.building:id,name', 'asset:id,asset_code', 'assignedPic:id,name'])
                ->orderBy('id')
                ->lazy(500);

            foreach ($reports as $report) {
                $writer->addRow(Row::fromValues(ReportExport::row($report)));
                $count++;
            }

            if ($count === 0) {
                $writer->addRow(Row::fromValues(['Tidak ada data']));
            }

            $writer->close();
        }, $name, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function pdf(Request $request): Response|RedirectResponse
    {
        Gate::authorize('viewAny', Report::class);

        $filter = ReportFilter::fromRequest($request);
        $query = $filter->apply(Report::query());

        $total = (clone $query)->count();
        $limit = (int) config('siperkasa.pdf_max_rows');

        if ($total > $limit) {
            return back()->withErrors(['export' => "Terlalu banyak data ({$total} baris) untuk PDF. Gunakan Excel atau persempit filter."]);
        }

        $counts = (clone $query)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $summary = [];
        foreach (ReportStatus::cases() as $status) {
            if (($counts[$status->value] ?? 0) > 0) {
                $summary[$status->label()] = (int) $counts[$status->value];
            }
        }

        $rows = $query->with(['category:id,name', 'room.building:id,name', 'assignedPic:id,name'])
            ->orderByDesc('created_at')->get()
            ->map(fn (Report $report) => [
                'Tiket' => $report->ticket_number,
                'Tanggal' => $report->created_at?->format('d M Y'),
                'Judul' => $report->title,
                'Lokasi' => trim(($report->room?->building?->name ?? '-').', '.($report->room?->name ?? '-')),
                'Status' => $report->status->label(),
                'PIC' => $report->assignedPic?->name ?? '-',
                'SLA' => $report->sla_date?->format('d M Y H:i') ?? '-',
            ])->all();

        $logoPath = public_path('images/logo-uinssc.png');

        $pdf = Pdf::loadView('exports.reports', [
            'logo' => is_file($logoPath) ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoPath)) : '',
            'printedAt' => now()->format('d M Y H:i'),
            'filterText' => ReportExport::filterText($filter->values),
            'summary' => $summary,
            'rows' => $rows,
        ])->setPaper('a4', 'landscape');

        // Page numbers ("Halaman 1 dari 3"): render first, then stamp every page.
        $pdf->render();
        $dompdf = $pdf->getDomPDF();
        $canvas = $dompdf->getCanvas();
        $canvas->page_text(
            $canvas->get_width() - 110,
            $canvas->get_height() - 24,
            'Halaman {PAGE_NUM} dari {PAGE_COUNT}',
            $dompdf->getFontMetrics()->getFont('DejaVu Sans', 'normal'),
            8,
            [0.34, 0.36, 0.35],
        );

        return $pdf->download('laporan-siperkasa-'.now()->format('Ymd-Hi').'.pdf');
    }
}
```

- [ ] **Step 6: Blade view** `resources/views/exports/reports.blade.php`

```blade
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan Kerusakan Aset</title>
    <style>
        @page { margin: 24px 28px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #07110b; }
        table { width: 100%; border-collapse: collapse; }
        .kop { border-bottom: 2px solid #0a5e3d; margin-bottom: 8px; padding-bottom: 6px; }
        .univ { font-size: 10px; font-weight: bold; color: #0f443e; }
        .title { font-size: 14px; font-weight: bold; color: #0a5e3d; margin-top: 2px; }
        .meta { color: #565c59; margin: 6px 0; }
        .summary td { border: 1px solid #d5dad7; padding: 4px 8px; text-align: center; }
        .summary .n { font-size: 12px; font-weight: bold; }
        .data { margin-top: 10px; }
        .data th { background: #0a5e3d; color: #fff; padding: 5px; text-align: left; }
        .data td { border-bottom: 1px solid #e6e8e7; padding: 4px 5px; vertical-align: top; }
        .data tr:nth-child(even) td { background: #f5f7f6; }
        .empty { text-align: center; padding: 16px; color: #565c59; }
        .footer { position: fixed; bottom: 0; right: 0; color: #565c59; }
    </style>
</head>
<body>
    <table class="kop">
        <tr>
            @if ($logo)
                <td style="width: 56px"><img src="{{ $logo }}" alt="Logo UINSSC" width="44"></td>
            @endif
            <td>
                <div class="univ">UNIVERSITAS ISLAM NEGERI SIBER SYEKH NURJATI CIREBON</div>
                <div class="title">Laporan Kerusakan Aset — SiPerkasa</div>
            </td>
        </tr>
    </table>

    <div class="meta">Dicetak: {{ $printedAt }} &nbsp;·&nbsp; Filter: {{ $filterText }}</div>

    @if (count($summary))
        <table class="summary">
            <tr>
                @foreach ($summary as $label => $total)
                    <td><div class="n">{{ $total }}</div>{{ $label }}</td>
                @endforeach
            </tr>
        </table>
    @endif

    <table class="data">
        <thead>
            <tr>
                <th>Tiket</th><th>Tanggal</th><th>Judul</th><th>Lokasi</th><th>Status</th><th>PIC</th><th>SLA</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['Tiket'] }}</td>
                    <td>{{ $row['Tanggal'] }}</td>
                    <td>{{ $row['Judul'] }}</td>
                    <td>{{ $row['Lokasi'] }}</td>
                    <td>{{ $row['Status'] }}</td>
                    <td>{{ $row['PIC'] }}</td>
                    <td>{{ $row['SLA'] }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty">Tidak ada data</td></tr>
            @endforelse
        </tbody>
    </table>

</body>
</html>
```

- [ ] **Step 7: Routes.** In `routes/web.php` add `use App\Http\Controllers\Admin\ExportController;` and, inside the `role:admin` group, **above** `reports/{report}`:

```php
        Route::get('reports/export/excel', [ExportController::class, 'excel'])->name('reports.export.excel');
        Route::get('reports/export/pdf', [ExportController::class, 'pdf'])->name('reports.export.pdf');
```

- [ ] **Step 8: Run the tests**

Run: `php artisan test tests/Feature/Admin/ExportExcelTest.php tests/Feature/Admin/ExportPdfTest.php`
Expected: all pass. (The page-number calls `getCanvas()`, `get_width()`, `get_height()`, `getFontMetrics()->getFont()` and `page_text()` were checked against the current dompdf while writing this plan; `barryvdh/laravel-dompdf` v3.1 supports Laravel 13.) If the PDF header test fails because the response body is empty, assert on `$response->baseResponse->getContent()`; if `streamedContent()` is empty for the Excel test, make sure no output buffering is disabled by `phpunit.xml` and that `Writer::openToFile('php://output')` is used (not `openToBrowser`).

- [ ] **Step 9: Export buttons on the admin list.** In `resources/js/pages/admin/reports/Index.vue` import `computed` from `vue`, `{ excel, pdf } from '@/routes/admin/reports/export'`, `usePage` from `@inertiajs/vue3`, and add icons `FileSpreadsheet, FileText` from `@lucide/vue`. Add:

```ts
const exportQuery = computed(() =>
    Object.fromEntries(Object.entries(props.filters).filter(([, value]) => value !== '' && value !== undefined && value !== false)),
);
const exportError = computed(() => usePage().props.errors?.export as string | undefined);
```

and, directly under the `PageHeader` line, add:

```vue
        <div class="flex flex-wrap gap-2">
            <Button as-child variant="outline" class="h-11">
                <a :href="excel.url({ query: exportQuery })"><FileSpreadsheet class="size-4" /> Export Excel</a>
            </Button>
            <Button as-child variant="outline" class="h-11">
                <a :href="pdf.url({ query: exportQuery })"><FileText class="size-4" /> Export PDF</a>
            </Button>
        </div>
        <p v-if="exportError" class="rounded-md border border-destructive/40 bg-status-rejected-bg p-3 text-sm text-status-rejected" role="alert">{{ exportError }}</p>
```

(Plain `<a>` tags are used on purpose: the response is a file download, not an Inertia page. Check the generated module path in `resources/js/routes/admin/reports/export/index.ts`; adjust the import if Wayfinder nests it differently.)

- [ ] **Step 10: Verify**

Run: `php artisan test && composer lint:check && composer types:check && php artisan wayfinder:generate && npm run types:check && npm run check && npm run build`
Expected: all pass.

- [ ] **Step 11: Manual check**

As admin, filter the list (status + date), click "Export Excel" → open the file: the header row, filtered rows, Rupiah costs as numbers, no cell starts with `=`. Click "Export PDF" → landscape A4 with the logo, filter text, status counts, table and page numbers. Temporarily lower `pdf_max_rows` to 1 → the page shows the red message and no download starts.

- [ ] **Step 12: Commit**

```bash
git add -A
git commit -m "feat: export reports to Excel (streamed) and PDF with the list filters"
```

---

### Task 4: Demo seeder and final acceptance run

**Files:**
- Create: `database/seeders/DemoReportSeeder.php`, `tests/Feature/DemoReportSeederTest.php`
- Modify: `database/seeders/DatabaseSeeder.php`

**Interfaces:**
- Produces: `DemoReportSeeder` — creates ~30 reports spread over the last 40 days in every status, with logs, using existing users, categories and rooms. Runs only from `DatabaseSeeder` in the `local` environment.

- [ ] **Step 1: Write the failing test** `tests/Feature/DemoReportSeederTest.php`

```php
<?php

use App\Enums\ReportStatus;
use App\Models\Category;
use App\Models\Report;
use App\Models\Room;
use App\Models\User;
use Database\Seeders\DemoReportSeeder;

test('the demo seeder fills every status with logs and valid tickets', function () {
    User::factory()->admin()->create();
    User::factory()->pic()->count(2)->create();
    User::factory()->count(3)->create();
    Category::factory()->count(3)->create();
    Room::factory()->count(3)->create();

    $this->seed(DemoReportSeeder::class);

    expect(Report::count())->toBe(30);

    foreach (ReportStatus::cases() as $status) {
        expect(Report::where('status', $status)->count())->toBeGreaterThan(0, "no demo report with status {$status->value}");
    }

    expect(Report::pluck('ticket_number')->unique()->count())->toBe(30);
    expect(Report::pluck('ticket_number')->every(fn ($t) => preg_match('/^SPK-\d{4}-\d{4}$/', $t) === 1))->toBeTrue();

    Report::with('statusLogs')->get()->each(function (Report $report) {
        expect($report->statusLogs)->not->toBeEmpty();
        expect($report->statusLogs->last()->to_status)->toBe($report->status->value);
    });
});

test('closed demo reports are completed after they were created and not in the future', function () {
    User::factory()->admin()->create();
    User::factory()->pic()->create();
    User::factory()->create();
    Category::factory()->create();
    Room::factory()->create();

    $this->seed(DemoReportSeeder::class);

    Report::where('status', ReportStatus::Closed)->get()->each(function (Report $report) {
        expect($report->closed_at->gte($report->created_at))->toBeTrue()
            ->and($report->closed_at->lte(now()))->toBeTrue()
            ->and($report->completed_on)->not->toBeNull();
    });
});

test('the seeder does nothing when the base data is missing', function () {
    $this->seed(DemoReportSeeder::class);

    expect(Report::count())->toBe(0);
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `php artisan test tests/Feature/DemoReportSeederTest.php`
Expected: FAIL (`Class "Database\Seeders\DemoReportSeeder" not found`).

- [ ] **Step 3: Create `database/seeders/DemoReportSeeder.php`**

```php
<?php

namespace Database\Seeders;

use App\Enums\Priority;
use App\Enums\ReportStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Report;
use App\Models\ReportStatusLog;
use App\Models\Room;
use App\Models\User;
use App\Support\TicketNumber;
use Illuminate\Database\Seeder;

/** Local demo data so dashboards, lists and exports have something to show. */
class DemoReportSeeder extends Seeder
{
    /** @var array<string, int> state => how many reports */
    private const PLAN = [
        'pending' => 6, 'assigned' => 5, 'inProgress' => 5, 'onHold' => 3, 'closed' => 8, 'rejected' => 2, 'cancelled' => 1,
    ];

    private const TITLES = [
        'Proyektor tidak menyala', 'AC tidak dingin', 'Kursi kuliah patah', 'Komputer lab restart sendiri',
        'Lampu ruangan mati', 'Whiteboard rusak', 'Mic wireless tidak berfungsi', 'Kabel LAN putus',
        'Pintu ruang kelas macet', 'Stop kontak korsleting',
    ];

    public function run(): void
    {
        $admin = User::where('role', UserRole::Admin)->first();
        $pics = User::where('role', UserRole::Pic)->where('is_active', true)->get();
        $reporters = User::where('role', UserRole::User)->where('is_active', true)->get();
        $categories = Category::all();
        $rooms = Room::all();

        if (! $admin || $pics->isEmpty() || $reporters->isEmpty() || $categories->isEmpty() || $rooms->isEmpty()) {
            return;
        }

        foreach (self::PLAN as $state => $count) {
            for ($i = 0; $i < $count; $i++) {
                $this->createReport($state, $admin, $pics->random(), $reporters->random(), $categories->random(), $rooms->random());
            }
        }
    }

    private function createReport(string $state, User $admin, User $pic, User $reporter, Category $category, Room $room): void
    {
        $createdAt = now()->subDays(random_int(0, 40))->subMinutes(random_int(0, 600));

        $overrides = [
            'ticket_number' => TicketNumber::next($createdAt),
            'user_id' => $reporter->id,
            'category_id' => $category->id,
            'room_id' => $room->id,
            'title' => self::TITLES[array_rand(self::TITLES)],
            'user_priority' => Priority::cases()[array_rand(Priority::cases())],
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ];

        // Passing admin_id stops the factory states from creating an extra admin user per report.
        if (! in_array($state, ['pending', 'cancelled'], true)) {
            $overrides['admin_id'] = $admin->id;
        }

        $factory = Report::factory();
        $factory = in_array($state, ['pending', 'rejected', 'cancelled'], true) ? $factory->{$state}() : $factory->{$state}($pic);

        $report = $factory->create($overrides);

        $validatedAt = $createdAt->copy()->addHours(random_int(1, 12));
        $closedAt = $validatedAt->copy()->addDays(random_int(1, 5));

        $attributes = match ($state) {
            'rejected', 'pending' => [],
            'cancelled' => ['cancelled_at' => $createdAt->copy()->addHour()],
            default => [
                'admin_priority' => $report->user_priority,
                'validated_at' => $validatedAt,
                'sla_date' => $validatedAt->copy()->addDays($report->user_priority->slaDays()),
                'started_at' => $state === 'assigned' ? null : $validatedAt->copy()->addHours(2),
                'closed_at' => $state === 'closed' ? min($closedAt, now()) : null,
                'completed_on' => $state === 'closed' ? min($closedAt, now())->toDateString() : null,
            ],
        };

        $report->forceFill($attributes)->save();

        $this->log($report, null, ReportStatus::Pending->value, $reporter, null, $createdAt);

        if ($state !== 'pending') {
            $this->log($report, ReportStatus::Pending->value, $report->status->value, $state === 'cancelled' ? $reporter : $admin, null, $validatedAt);
        }
    }

    private function log(Report $report, ?string $from, string $to, User $actor, ?string $note, \Carbon\CarbonInterface $at): void
    {
        $log = ReportStatusLog::create(['report_id' => $report->id, 'from_status' => $from, 'to_status' => $to, 'actor_id' => $actor->id, 'note' => $note]);
        $log->forceFill(['created_at' => $at])->save();
    }
}
```

Note: in the test above, `statusLogs->last()->to_status` equals the report status because the second log (when the state is not `pending`) records the current status.

- [ ] **Step 4: Call it from `DatabaseSeeder`.** In `database/seeders/DatabaseSeeder.php`, at the end of the `local`-only part (after the demo-users `foreach`), add:

```php
        $this->call(DemoReportSeeder::class);
```

- [ ] **Step 5: Run the seeder test**

Run: `php artisan test tests/Feature/DemoReportSeederTest.php`
Expected: 3 passed.

- [ ] **Step 6: Final acceptance run**

Run:

```bash
php artisan migrate:fresh --seed
php artisan assets:import data/daftar-aset-gabungan-terkategori.xlsx
php artisan test
composer lint:check && composer types:check
php artisan wayfinder:generate && npm run types:check && npm run check && npm run build
```

Expected: seeded database with demo reports, 25,995 assets, every test green, no lint or type errors, a successful production build.

- [ ] **Step 7: Walk through the F01–F11 acceptance criteria** using `composer dev` with the three demo logins (`admin` / `pic001` / `2010001`, password `password`) and the checklists in `C:\Users\Lenovo\Documents\Obsidian Vault\40 - PROJECTS\SiPerkasa\03-Tasks\F01…F11`. For F11, run Lighthouse (mobile) on login, create-report and admin report list; expected accessibility score ≥ 90.

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "feat: demo report seeder and final acceptance run"
```

---

## Self-Review (Plan 5)

- **Spec coverage:** F08 AC1–AC3 (recipients per step, real notifications table) → Task 1 `ReportLifecycleNotificationsTest`; AC4 (badge 3) → `NotificationInboxTest`; AC5 (click marks read and opens the report) → same; AC6 (mark all) → same; AC7 (someone else's → 404) → same. F09 AC1 (user counts) → Task 2; AC2 (PIC overdue first) → Task 2; AC3 (average = 2 days for 1/2/3-day reports) → Task 2; AC4 (7-day filter) → Task 2; AC5 (bar → filtered list) → Task 2 UI links + manual; AC6 (empty state) → Task 2 `an empty database…`. F10 AC1 (filtered Excel, all columns) → Task 3; AC2 (PDF with logo/filter/summary/table) → Task 3; AC3 (>1000 rows refused) → Task 3 (limit lowered in the test); AC4 (403) → Task 3; AC5 (empty export) → Task 3.
- **Placeholder scan:** none.
- **Type consistency:** `ReportFilter`, `ReportPresenter::summary`, `StatusBadge`, `PageHeader`, `EmptyState`, `Pagination` are used exactly as defined in Plans 1–4; `DashboardStats` shapes match the Vue prop types.
- **Review Focus:** all five lines have tests (Tasks 1, 2, 3).
- **Deviations:** charts are CSS bars rather than a chart library; Excel export uses OpenSpout (see index).
