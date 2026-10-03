<?php

use App\Actions\Reports\SubmitReport;
use App\Enums\AttachmentType;
use App\Enums\ReportStatus;
use App\Models\Asset;
use App\Models\Report;
use App\Models\User;
use App\Notifications\ReportSubmitted;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ReportInput;

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
    $this->reporter = User::factory()->create();
});

test('the create form lists categories, buildings with rooms and priorities', function () {
    $this->actingAs($this->reporter)->get(route('reports.create'))
        ->assertInertia(fn ($page) => $page
            ->component('reports/Create')
            ->has('categories')
            ->has('buildings')
            ->has('priorities', 4));
});

test('submitting stores a pending report with ticket, photo and log', function () {
    $response = $this->actingAs($this->reporter)->post(route('reports.store'), ReportInput::valid());

    $report = Report::sole();
    $response->assertRedirect(route('reports.show', $report));

    expect($report->status)->toBe(ReportStatus::Pending)
        ->and($report->user_id)->toBe($this->reporter->id)
        ->and($report->ticket_number)->toMatch('/^SPK-\d{4}-0001$/')
        ->and($report->asset_id)->toBeNull();

    $photo = $report->attachments()->sole();
    expect($photo->type)->toBe(AttachmentType::Damage)->and($photo->position)->toBe(1);
    Storage::disk('local')->assertExists($photo->path);

    $log = $report->statusLogs()->sole();
    expect($log->from_status)->toBeNull()->and($log->to_status)->toBe('Pending')->and($log->actor_id)->toBe($this->reporter->id);
});

test('a second report gets the next ticket', function () {
    $this->actingAs($this->reporter)->post(route('reports.store'), ReportInput::valid());
    $this->actingAs($this->reporter)->post(route('reports.store'), ReportInput::valid());

    expect(Report::orderBy('id')->pluck('ticket_number')->map(fn ($t) => substr($t, -4))->all())->toBe(['0001', '0002']);
});

test('a ticket collision is retried and the submit succeeds with another ticket', function () {
    $input = ReportInput::valid();
    $photos = $input['photos'];
    unset($input['photos']);

    // A report already holds the first ticket of the month.
    $taken = Report::factory()->create(['ticket_number' => 'SPK-'.now()->format('ym').'-0001']);

    // Simulate a stale ticket: the first insert attempt reuses the taken number.
    $calls = 0;
    Report::creating(function (Report $report) use (&$calls, $taken) {
        if ($calls++ === 0) {
            $report->ticket_number = $taken->ticket_number;
        }
    });

    $report = app(SubmitReport::class)->handle($this->reporter, $input, $photos);

    expect($calls)->toBe(2)
        ->and($report->ticket_number)->toBe('SPK-'.now()->format('ym').'-0002')
        ->and(Report::count())->toBe(2)
        ->and($report->attachments()->count())->toBe(1)
        ->and(Storage::disk('local')->allFiles())->toHaveCount(1);
});

test('every active admin is notified, inactive admins and others are not', function () {
    $admins = User::factory()->admin()->count(2)->create();
    $inactive = User::factory()->admin()->inactive()->create();
    $pic = User::factory()->pic()->create();

    $this->actingAs($this->reporter)->post(route('reports.store'), ReportInput::valid());

    Notification::assertSentTo($admins, ReportSubmitted::class);
    Notification::assertNotSentTo([$inactive, $pic, $this->reporter], ReportSubmitted::class);
});

test('an asset is optional and can be attached', function () {
    $asset = Asset::factory()->create();

    $this->actingAs($this->reporter)->post(route('reports.store'), ReportInput::valid(['asset_id' => $asset->id]));

    expect(Report::sole()->asset_id)->toBe($asset->id);
});

test('required fields are validated with Indonesian messages', function () {
    $this->actingAs($this->reporter)->post(route('reports.store'), [])
        ->assertSessionHasErrors([
            'title' => 'Judul wajib diisi.',
            'category_id' => 'Kategori wajib diisi.',
            'room_id' => 'Ruang wajib diisi.',
            'description' => 'Deskripsi wajib diisi.',
            'user_priority' => 'Urgensi wajib diisi.',
            'photos' => 'Minimal 1 foto kerusakan.',
        ]);

    expect(Report::count())->toBe(0);
});

test('the title may be exactly 120 characters, counting multibyte characters once', function () {
    $this->actingAs($this->reporter)->post(route('reports.store'), ReportInput::valid(['title' => str_repeat('é', 120)]))
        ->assertSessionHasNoErrors();
    expect(Report::count())->toBe(1);

    $this->actingAs($this->reporter)->post(route('reports.store'), ReportInput::valid(['title' => str_repeat('é', 121)]))
        ->assertSessionHasErrors('title');
    expect(Report::count())->toBe(1);
});

test('the description needs 10 to 2000 characters', function () {
    $this->actingAs($this->reporter)->post(route('reports.store'), ReportInput::valid(['description' => 'pendek']))->assertSessionHasErrors('description');
    $this->actingAs($this->reporter)->post(route('reports.store'), ReportInput::valid(['description' => str_repeat('a', 2001)]))->assertSessionHasErrors('description');
    $this->actingAs($this->reporter)->post(route('reports.store'), ReportInput::valid(['description' => str_repeat('a', 2000)]))->assertSessionHasNoErrors();
});

test('photos: one to three images up to 2048 KB', function () {
    $post = fn (array $photos) => $this->actingAs($this->reporter)->post(route('reports.store'), ReportInput::valid(['photos' => $photos]));
    $image = fn (string $name = 'a.jpg', int $kb = 100) => UploadedFile::fake()->image($name)->size($kb);

    $post([])->assertSessionHasErrors(['photos' => 'Minimal 1 foto kerusakan.']);
    $post([$image(), $image('b.jpg'), $image('c.png'), $image('d.png')])->assertSessionHasErrors(['photos' => 'Maksimal 3 foto.']);
    $post([$image(kb: 2049)])->assertSessionHasErrors('photos.0');
    $post([UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf')])->assertSessionHasErrors('photos.0');

    expect(Report::count())->toBe(0);

    $post([$image(kb: 2048), $image('b.png'), $image('c.jpg')])->assertSessionHasNoErrors();
    expect(Report::sole()->attachments()->count())->toBe(3);
});

test('the room, category and asset must exist', function () {
    $this->actingAs($this->reporter)->post(route('reports.store'), ReportInput::valid(['room_id' => 9999, 'category_id' => 9999, 'asset_id' => 9999]))
        ->assertSessionHasErrors(['room_id', 'category_id', 'asset_id']);
});

test('admins and pics can not create reports', function (string $state) {
    $this->actingAs(User::factory()->{$state}()->create())
        ->post(route('reports.store'), ReportInput::valid())->assertForbidden();

    expect(Report::count())->toBe(0);
})->with(['admin', 'pic']);

test('guests are sent to the login page', function () {
    $this->get(route('reports.create'))->assertRedirect(route('login'));
});
