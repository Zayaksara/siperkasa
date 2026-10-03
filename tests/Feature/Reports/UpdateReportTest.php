<?php

use App\Actions\Reports\UpdateReport;
use App\Enums\AttachmentType;
use App\Models\Category;
use App\Models\Report;
use App\Models\ReportAttachment;
use App\Models\ReportStatusLog;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Support\ReportInput;

beforeEach(function () {
    Storage::fake('local');
    $this->owner = User::factory()->create();
    $this->report = Report::factory()->for($this->owner)->create(['title' => 'Judul lama']);
    $this->photo = ReportAttachment::factory()->for($this->report)->create(['type' => AttachmentType::Damage, 'path' => 'reports/1/damage/lama.jpg']);
    Storage::disk('local')->put($this->photo->path, 'x');
});

function reportUpdatePayload(array $overrides = []): array
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

    $this->actingAs($this->owner)->post(route('reports.update', $this->report), reportUpdatePayload(['title' => 'Judul baru', 'category_id' => $category->id]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('reports.show', $this->report));

    expect($this->report->fresh()->title)->toBe('Judul baru')
        ->and($this->report->fresh()->category_id)->toBe($category->id)
        ->and($this->report->fresh()->status->value)->toBe('Pending');

    expect($this->report->statusLogs()->get()->last()->note)->toBe('Laporan diubah');
});

test('new photos can be added up to three in total', function () {
    $this->actingAs($this->owner)->post(route('reports.update', $this->report), [
        ...reportUpdatePayload(),
        'photos' => [UploadedFile::fake()->image('b.jpg'), UploadedFile::fake()->image('c.jpg')],
    ])->assertSessionHasNoErrors();

    expect($this->report->damagePhotos()->count())->toBe(3);

    $this->actingAs($this->owner)->post(route('reports.update', $this->report), [
        ...reportUpdatePayload(),
        'photos' => [UploadedFile::fake()->image('d.jpg')],
    ])->assertSessionHasErrors(['photos' => 'Maksimal 3 foto.']);
});

test('an existing photo can be replaced', function () {
    $this->actingAs($this->owner)->post(route('reports.update', $this->report), [
        ...reportUpdatePayload(),
        'remove_photo_ids' => [$this->photo->id],
        'photos' => [UploadedFile::fake()->image('baru.jpg')],
    ])->assertSessionHasNoErrors();

    expect(ReportAttachment::find($this->photo->id))->toBeNull();
    Storage::disk('local')->assertMissing('reports/1/damage/lama.jpg');
    expect($this->report->damagePhotos()->count())->toBe(1);
});

test('removing every photo without adding one is refused and the photo stays', function () {
    $this->actingAs($this->owner)->post(route('reports.update', $this->report), [
        ...reportUpdatePayload(),
        'remove_photo_ids' => [$this->photo->id],
    ])->assertSessionHasErrors(['photos' => 'Minimal 1 foto kerusakan.']);

    expect(ReportAttachment::find($this->photo->id))->not->toBeNull();
    Storage::disk('local')->assertExists($this->photo->path);
});

test('photo ids of another report can not be removed through this report', function () {
    $foreign = ReportAttachment::factory()->create(['path' => 'reports/2/damage/lain.jpg']);
    Storage::disk('local')->put($foreign->path, 'x');

    $this->actingAs($this->owner)->post(route('reports.update', $this->report), [
        ...reportUpdatePayload(),
        'remove_photo_ids' => [$foreign->id],
        'photos' => [UploadedFile::fake()->image('b.jpg')],
    ])->assertSessionHasNoErrors()->assertRedirect(route('reports.show', $this->report));

    expect(ReportAttachment::find($foreign->id))->not->toBeNull();
    Storage::disk('local')->assertExists($foreign->path);
    expect($this->report->damagePhotos()->count())->toBe(2);
});

test('added photos are stored under the report damage folder', function () {
    $this->actingAs($this->owner)->post(route('reports.update', $this->report), [
        ...reportUpdatePayload(),
        'photos' => [UploadedFile::fake()->image('b.jpg')],
    ])->assertSessionHasNoErrors();

    $added = $this->report->damagePhotos()->get()->last();

    expect($added->path)->toStartWith("reports/{$this->report->id}/damage/")
        ->and($added->position)->toBe(2);
    Storage::disk('local')->assertExists($added->path);
});

test('a photo-only edit still writes a log row', function () {
    $this->actingAs($this->owner)->post(route('reports.update', $this->report), [
        ...reportUpdatePayload([
            'title' => $this->report->title,
            'category_id' => $this->report->category_id,
            'room_id' => $this->report->room_id,
            'description' => $this->report->description,
            'user_priority' => $this->report->user_priority->value,
        ]),
        'photos' => [UploadedFile::fake()->image('b.jpg')],
    ])->assertSessionHasNoErrors();

    expect($this->report->statusLogs()->count())->toBe(1)
        ->and($this->report->statusLogs()->first()->note)->toBe('Laporan diubah');
});

test('with three photos, removing one and adding three is refused', function () {
    $second = ReportAttachment::factory()->for($this->report)->create(['type' => AttachmentType::Damage, 'path' => 'reports/1/damage/dua.jpg']);
    $third = ReportAttachment::factory()->for($this->report)->create(['type' => AttachmentType::Damage, 'path' => 'reports/1/damage/tiga.jpg']);
    Storage::disk('local')->put($second->path, 'x');
    Storage::disk('local')->put($third->path, 'x');

    $this->actingAs($this->owner)->post(route('reports.update', $this->report), [
        ...reportUpdatePayload(),
        'remove_photo_ids' => [$this->photo->id],
        'photos' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg'), UploadedFile::fake()->image('c.jpg')],
    ])->assertSessionHasErrors(['photos' => 'Maksimal 3 foto.']);

    expect($this->report->damagePhotos()->count())->toBe(3);
    Storage::disk('local')->assertExists($this->photo->path);
    expect(Storage::disk('local')->allFiles('reports'))->toEqualCanonicalizing([$this->photo->path, $second->path, $third->path]);
});

test('the action rechecks the photo count under the lock', function () {
    $loaded = Report::findOrFail($this->report->id);

    expect(fn () => app(UpdateReport::class)->handle($loaded, $this->owner, ['title' => 'Baru'], [], [$this->photo->id]))
        ->toThrow(ValidationException::class, 'Minimal 1 foto kerusakan.');

    $extra = [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg'), UploadedFile::fake()->image('c.jpg')];

    expect(fn () => app(UpdateReport::class)->handle($loaded, $this->owner, ['title' => 'Baru'], $extra, []))
        ->toThrow(ValidationException::class, 'Maksimal 3 foto.');

    expect($this->report->fresh()->title)->toBe('Judul lama')
        ->and($this->report->damagePhotos()->count())->toBe(1);
    Storage::disk('local')->assertExists($this->photo->path);
    expect(Storage::disk('local')->allFiles('reports'))->toBe([$this->photo->path]);
});

test('when saving the new photo row fails the old file stays and no new file is left', function () {
    ReportAttachment::creating(fn () => throw new RuntimeException('db down'));

    $loaded = Report::findOrFail($this->report->id);

    expect(fn () => app(UpdateReport::class)->handle($loaded, $this->owner, ['title' => 'Baru'], [UploadedFile::fake()->image('b.jpg')], [$this->photo->id]))
        ->toThrow(RuntimeException::class, 'db down');

    expect($this->report->fresh()->title)->toBe('Judul lama')
        ->and(ReportAttachment::find($this->photo->id))->not->toBeNull();
    Storage::disk('local')->assertExists($this->photo->path);
    expect(Storage::disk('local')->allFiles('reports'))->toBe([$this->photo->path]);
});

test('when writing the log fails the old file stays and no new file is left', function () {
    ReportStatusLog::creating(fn () => throw new RuntimeException('log down'));

    $loaded = Report::findOrFail($this->report->id);

    expect(fn () => app(UpdateReport::class)->handle($loaded, $this->owner, ['title' => 'Baru'], [UploadedFile::fake()->image('b.jpg')], [$this->photo->id]))
        ->toThrow(RuntimeException::class, 'log down');

    expect($this->report->fresh()->title)->toBe('Judul lama')
        ->and(ReportAttachment::find($this->photo->id))->not->toBeNull();
    Storage::disk('local')->assertExists($this->photo->path);
    expect(Storage::disk('local')->allFiles('reports'))->toBe([$this->photo->path]);
});

test('a stale form is refused once the report is no longer pending', function () {
    $this->report->update(['status' => 'Assigned']);

    $this->actingAs($this->owner)->post(route('reports.update', $this->report), reportUpdatePayload(['title' => 'Terlambat']))->assertForbidden();
    $this->actingAs($this->owner)->get(route('reports.edit', $this->report))->assertForbidden();

    expect($this->report->fresh()->title)->toBe('Judul lama');
});

test('a stale form that also removes the only photo gets 403 and nothing changes', function () {
    $this->report->update(['status' => 'Assigned']);

    $this->actingAs($this->owner)->post(route('reports.update', $this->report), [
        ...reportUpdatePayload(['title' => 'Terlambat']),
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
    $this->actingAs(User::factory()->create())->post(route('reports.update', $this->report), reportUpdatePayload())->assertForbidden();
    $this->actingAs(User::factory()->admin()->create())->post(route('reports.update', $this->report), reportUpdatePayload())->assertForbidden();
});

test('validation rules of the create form apply to edits too', function () {
    $this->actingAs($this->owner)->post(route('reports.update', $this->report), reportUpdatePayload(['title' => '', 'description' => 'x']))
        ->assertSessionHasErrors(['title', 'description']);
});
