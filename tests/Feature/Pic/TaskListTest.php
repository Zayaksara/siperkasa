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

test('the active tab can filter by status', function () {
    Report::factory()->assigned($this->pic)->create();
    $held = Report::factory()->onHold($this->pic)->create();

    $this->actingAs($this->pic)->get(route('pic.tasks.index', ['status' => 'On_Hold']))
        ->assertInertia(fn ($page) => $page
            ->has('tasks.data', 1)
            ->where('tasks.data.0.id', $held->id)
            ->where('filters.status', 'On_Hold')
            ->where('counts.active', 2));
});

test('the active tab can filter overdue tasks', function () {
    $late = Report::factory()->inProgress($this->pic)->create(['sla_date' => now()->subDay()]);
    Report::factory()->assigned($this->pic)->create(['sla_date' => now()->addDay()]);

    $this->actingAs($this->pic)->get(route('pic.tasks.index', ['overdue' => 1]))
        ->assertInertia(fn ($page) => $page
            ->has('tasks.data', 1)
            ->where('tasks.data.0.id', $late->id)
            ->where('filters.overdue', true));
});

test('an invalid pic status filter is a validation error', function () {
    $this->actingAs($this->pic)->get(route('pic.tasks.index', ['status' => 'Closed']))->assertSessionHasErrors('status');
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

test('a pic sees their task detail but not another pic\'s', function () {
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
