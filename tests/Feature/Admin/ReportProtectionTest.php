<?php

use App\Models\Category;
use App\Models\Report;
use App\Models\Room;
use App\Models\User;

beforeEach(fn () => $this->admin = User::factory()->admin()->create());

test('admin cannot delete a category used by a report', function () {
    $category = Category::factory()->create();
    Report::factory()->for($category)->create();

    $this->actingAs($this->admin)->delete(route('admin.categories.destroy', $category));

    expect($category->fresh())->not->toBeNull();
});

test('admin cannot delete a room used by a report', function () {
    $room = Room::factory()->create();
    Report::factory()->for($room)->create();

    $this->actingAs($this->admin)->delete(route('admin.rooms.destroy', $room));

    expect($room->fresh())->not->toBeNull();
});
