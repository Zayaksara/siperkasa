<?php

use App\Actions\Reports\UpdateReport;
use App\Enums\AttachmentType;
use App\Models\Category;
use App\Models\Report;
use App\Models\ReportAttachment;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
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

test('photo ids of another report can not be removed through this report', function () {
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

test('a stale form that also removes the only photo gets 403 and nothing changes', function () {
    $this->report->update(['status' => 'Assigned']);

    $this->actingAs($this->owner)->post(route('reports.update', $this->report), [
        ...updatePayload(['title' => 'Terlambat']),
        'remove_photo_ids' => [$this->photo->id],
    ])->assertForbidden();

    expect($this->report->fresh()->title)->toBe('Judul lama')
        ->and(ReportAttachment::find($this->photo->id))->not->toBeNull();
    Storage::disk('local')->assertExists($this->photo->path);
    expect($this->report->statusLogs()->count())->toBe(0);
});

test('the action refuses a report that left pending after it was loaded', function () {
    $loaded = Report::findOrFail($this->report->id);
    Report::whereKey($this->report->id)->update(['status' => 'Assigned']);

    expect(fn () => app(UpdateReport::class)->handle($loaded, $this->owner, ['title' => 'Terlambat'], [], [$this->photo->id]))
        ->toThrow(AuthorizationException::class);

    expect($this->report->fresh()->title)->toBe('Judul lama')
        ->and(ReportAttachment::find($this->photo->id))->not->toBeNull();
    Storage::disk('local')->assertExists($this->photo->path);
});

test('only the owner can edit', function () {
    $this->actingAs(User::factory()->create())->post(route('reports.update', $this->report), updatePayload())->assertForbidden();
    $this->actingAs(User::factory()->admin()->create())->post(route('reports.update', $this->report), updatePayload())->assertForbidden();
});

test('validation rules of the create form apply to edits too', function () {
    $this->actingAs($this->owner)->post(route('reports.update', $this->report), updatePayload(['title' => '', 'description' => 'x']))
        ->assertSessionHasErrors(['title', 'description']);
});
