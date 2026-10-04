<?php

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

test('the status filter accepts several comma-separated statuses', function () {
    Report::factory()->for($this->me)->rejected()->create();
    Report::factory()->for($this->me)->cancelled()->create();
    Report::factory()->for($this->me)->closed()->create();

    $this->actingAs($this->me)->get(route('reports.index', ['status' => 'Rejected,Cancelled']))
        ->assertInertia(fn ($page) => $page->has('reports.data', 2));
});

test('a bogus entry in a multi-status filter is a validation error', function () {
    $this->actingAs($this->me)->get(route('reports.index', ['status' => 'Rejected,Bogus']))->assertSessionHasErrors('status');
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

test('a report whose PIC was soft-deleted stays in the list with the PIC name', function () {
    $pic = User::factory()->pic()->create(['name' => 'Pak Teknisi']);
    Report::factory()->for($this->me)->assigned($pic)->create();
    $pic->delete();

    $this->actingAs($this->me)->get(route('reports.index'))
        ->assertInertia(fn ($page) => $page->has('reports.data', 1)->where('reports.data.0.pic', 'Pak Teknisi'));
});

test('an invalid status filter returns an Indonesian message', function () {
    $this->actingAs($this->me)->get(route('reports.index', ['status' => 'Bogus']))
        ->assertSessionHasErrors(['status' => 'Status yang dipilih tidak valid.']);
});
