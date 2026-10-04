<?php

use App\Models\Report;
use App\Models\User;

test('the pic dashboard counts tasks, overdue and this month\'s completions', function () {
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
