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

test('filter validation errors use Indonesian field names', function () {
    $response = $this->actingAs($this->admin)->get(route('admin.reports.index', ['to' => '2026-01-01', 'from' => '2026-02-01']));

    $response->assertSessionHasErrors(['to' => 'Tanggal akhir harus sama dengan atau setelah tanggal mulai.']);
});
