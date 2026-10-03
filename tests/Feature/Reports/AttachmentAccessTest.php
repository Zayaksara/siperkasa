<?php

use App\Models\Report;
use App\Models\ReportAttachment;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ReportInput;

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
    $this->actingAs($this->owner)->post(route('reports.store'), ReportInput::valid());

    $path = Report::latest('id')->first()->attachments()->sole()->path;

    Storage::disk('local')->assertExists($path);
    expect($this->get('/storage/'.$path)->status())->not->toBe(200);
});
