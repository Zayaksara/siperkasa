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
