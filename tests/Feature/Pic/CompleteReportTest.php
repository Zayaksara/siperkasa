<?php

use App\Actions\Reports\CompleteReport;
use App\Actions\Reports\InvalidTransition;
use App\Enums\AttachmentType;
use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\User;
use App\Notifications\ReportStatusChanged;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
    $this->pic = User::factory()->pic()->create();
    $this->report = Report::factory()->inProgress($this->pic)->create();
});

function picCompletionPayload(array $overrides = []): array
{
    return array_merge([
        'repair_action' => 'Mengganti lampu proyektor dan membersihkan filter.',
        'completed_on' => now()->toDateString(),
        'repair_cost' => 350000,
        'vendor_name' => 'CV Teknik Jaya',
        'photos' => [UploadedFile::fake()->image('selesai.jpg')],
    ], $overrides);
}

test('completing closes the report with proof and notifies the reporter', function () {
    $this->actingAs($this->pic)->post(route('pic.tasks.complete', $this->report), picCompletionPayload())
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('pic.tasks.index'));

    $report = $this->report->fresh();
    expect($report->status)->toBe(ReportStatus::Closed)
        ->and($report->repair_action)->toBe('Mengganti lampu proyektor dan membersihkan filter.')
        ->and($report->completed_on->toDateString())->toBe(now()->toDateString())
        ->and($report->repair_cost)->toBe('350000.00')
        ->and($report->vendor_name)->toBe('CV Teknik Jaya')
        ->and($report->closed_at)->not->toBeNull();

    $photo = $report->attachments()->where('type', AttachmentType::Completion)->sole();
    Storage::disk('local')->assertExists($photo->path);

    $log = $report->statusLogs()->get()->last();
    expect($log->to_status)->toBe('Closed')->and($log->actor_id)->toBe($this->pic->id);

    Notification::assertSentTo($report->user, ReportStatusChanged::class, fn ($n) => $n->report->status === ReportStatus::Closed);
});

test('cost and vendor are optional', function () {
    $this->actingAs($this->pic)->post(route('pic.tasks.complete', $this->report), picCompletionPayload(['repair_cost' => null, 'vendor_name' => null]))
        ->assertSessionHasNoErrors();

    $report = $this->report->fresh();
    expect($report->repair_cost)->toBeNull()->and($report->vendor_name)->toBeNull()->and($report->status)->toBe(ReportStatus::Closed);
});

test('repair cost accepts zero and decimals but not negatives', function () {
    $post = fn ($cost) => $this->actingAs($this->pic)->post(route('pic.tasks.complete', $this->report), picCompletionPayload(['repair_cost' => $cost]));

    $post(-1)->assertSessionHasErrors('repair_cost');
    $post('abc')->assertSessionHasErrors('repair_cost');
    expect($this->report->fresh()->status)->toBe(ReportStatus::InProgress);

    $post('1500000.50')->assertSessionHasNoErrors();
    expect($this->report->fresh()->repair_cost)->toBe('1500000.50');
});

test('zero is a valid cost', function () {
    $this->actingAs($this->pic)->post(route('pic.tasks.complete', $this->report), picCompletionPayload(['repair_cost' => 0]))->assertSessionHasNoErrors();

    expect($this->report->fresh()->repair_cost)->toBe('0.00');
});

test('the repair action is required', function () {
    $this->actingAs($this->pic)->post(route('pic.tasks.complete', $this->report), picCompletionPayload(['repair_action' => '']))
        ->assertSessionHasErrors(['repair_action' => 'Tindakan perbaikan wajib diisi.']);
});

test('at least one photo and at most three are required', function () {
    $post = fn (array $photos) => $this->actingAs($this->pic)->post(route('pic.tasks.complete', $this->report), picCompletionPayload(['photos' => $photos]));
    $image = fn (string $name) => UploadedFile::fake()->image($name);

    $post([])->assertSessionHasErrors(['photos' => 'Minimal 1 foto bukti pengerjaan.']);
    $post([$image('a.jpg'), $image('b.jpg'), $image('c.jpg'), $image('d.jpg')])->assertSessionHasErrors(['photos' => 'Maksimal 3 foto.']);
    $post([UploadedFile::fake()->create('x.pdf', 5, 'application/pdf')])->assertSessionHasErrors('photos.0');

    expect($this->report->fresh()->status)->toBe(ReportStatus::InProgress);
});

test('the completion date can not be in the future or before the report was created', function () {
    $post = fn (string $date) => $this->actingAs($this->pic)->post(route('pic.tasks.complete', $this->report), picCompletionPayload(['completed_on' => $date]));

    $post(now()->addDay()->toDateString())->assertSessionHasErrors('completed_on');
    $post($this->report->created_at->copy()->subDay()->toDateString())->assertSessionHasErrors('completed_on');

    expect($this->report->fresh()->status)->toBe(ReportStatus::InProgress);

    $post($this->report->created_at->toDateString())->assertSessionHasNoErrors();
});

test('only tasks in progress can be completed', function (string $state) {
    $report = Report::factory()->{$state}($this->pic)->create();

    $this->actingAs($this->pic)->post(route('pic.tasks.complete', $report), picCompletionPayload())->assertForbidden();

    expect($report->fresh()->status->value)->not->toBe('Closed');
})->with(['assigned', 'onHold']);

test('a closed task can not be completed twice', function () {
    $this->actingAs($this->pic)->post(route('pic.tasks.complete', $this->report), picCompletionPayload());
    $this->actingAs($this->pic)->post(route('pic.tasks.complete', $this->report), picCompletionPayload(['repair_action' => 'Tindakan kedua yang berbeda.']))->assertForbidden();

    expect($this->report->fresh()->repair_action)->toBe('Mengganti lampu proyektor dan membersihkan filter.');
});

test('another pic can not complete the task', function () {
    $this->actingAs(User::factory()->pic()->create())->post(route('pic.tasks.complete', $this->report), picCompletionPayload())->assertForbidden();
});

test('a stale completion changes nothing and stores no files', function () {
    Report::whereKey($this->report->id)->update(['status' => ReportStatus::OnHold]); // row moved on, model stale

    expect(fn () => app(CompleteReport::class)->handle($this->report, $this->pic, [
        'repair_action' => 'Mengganti lampu proyektor.',
        'completed_on' => now()->toDateString(),
    ], [UploadedFile::fake()->image('selesai.jpg')]))->toThrow(InvalidTransition::class);

    $report = $this->report->fresh();
    expect($report->status)->toBe(ReportStatus::OnHold)
        ->and($report->repair_action)->toBeNull()
        ->and($report->attachments()->count())->toBe(0)
        ->and($report->statusLogs()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
    Notification::assertNothingSent();
});

test('a pic who lost the task to a reassignment can not complete it', function () {
    $other = User::factory()->pic()->create();
    Report::whereKey($this->report->id)->update(['assigned_pic_id' => $other->id]); // reassigned after the policy check, model stale

    expect(fn () => app(CompleteReport::class)->handle($this->report, $this->pic, [
        'repair_action' => 'Mengganti lampu proyektor.',
        'completed_on' => now()->toDateString(),
    ], [UploadedFile::fake()->image('selesai.jpg')]))->toThrow(InvalidTransition::class);

    $report = $this->report->fresh();
    expect($report->status)->toBe(ReportStatus::InProgress)
        ->and($report->repair_action)->toBeNull()
        ->and($report->attachments()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
    Notification::assertNothingSent();
});
