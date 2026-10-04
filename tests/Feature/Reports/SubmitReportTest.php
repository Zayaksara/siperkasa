<?php

use App\Actions\Reports\SubmitReport;
use App\Enums\AttachmentType;
use App\Enums\ReportStatus;
use App\Models\Asset;
use App\Models\Report;
use App\Models\User;
use App\Notifications\ReportSubmitted;
use App\Support\ReportSubmissionLimit;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
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

test('a photo store failure leaves no report, log, attachment, file or notification behind', function () {
    $input = ReportInput::valid();
    unset($input['photos']);

    $failing = new class('boom.jpg', 100, 'image/jpeg') extends UploadedFile
    {
        public function __construct(string $name, int $size, string $mime)
        {
            parent::__construct(UploadedFile::fake()->image($name)->getPathname(), $name, $mime, null, true);
        }

        public function store($path = '', $options = []): string|false
        {
            throw new RuntimeException('disk full');
        }
    };

    expect(fn () => app(SubmitReport::class)->handle($this->reporter, $input, [UploadedFile::fake()->image('ok.jpg'), $failing]))
        ->toThrow(RuntimeException::class, 'disk full');

    expect(Report::count())->toBe(0)
        ->and(DB::table('report_status_logs')->count())->toBe(0)
        ->and(DB::table('report_attachments')->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBeEmpty();
    Notification::assertNothingSent();
});

test('a ticket collision that keeps happening is rethrown after three attempts', function () {
    $input = ReportInput::valid();
    $photos = $input['photos'];
    unset($input['photos']);

    $taken = Report::factory()->create(['ticket_number' => 'SPK-'.now()->format('ym').'-0001']);

    $calls = 0;
    Report::creating(function (Report $report) use (&$calls, $taken) {
        $calls++;
        $report->ticket_number = $taken->ticket_number;
    });

    expect(fn () => app(SubmitReport::class)->handle($this->reporter, $input, $photos))
        ->toThrow(UniqueConstraintViolationException::class);

    expect($calls)->toBe(3)
        ->and(Report::count())->toBe(1)
        ->and(Storage::disk('local')->allFiles())->toBeEmpty();
    Notification::assertNothingSent();
});

test('title needs minimum 10 characters', function () {
    $this->actingAs($this->reporter)->post(route('reports.store'), ReportInput::valid(['title' => 'short']))
        ->assertSessionHasErrors(['title' => 'Judul minimal 10 karakter. Sebut barang, kerusakan, dan lokasi singkat.']);

    expect(Report::count())->toBe(0);

    $this->actingAs($this->reporter)->post(route('reports.store'), ReportInput::valid(['title' => str_repeat('a', 10)]))
        ->assertSessionHasNoErrors();

    expect(Report::count())->toBe(1);
});

test('5 pending reports blocks 6th submission', function () {
    Report::factory()->pending()->count(5)->for($this->reporter, 'user')->create();

    $this->actingAs($this->reporter)->post(route('reports.store'), ReportInput::valid())
        ->assertSessionHasErrors(['title' => 'Kamu masih punya 5 laporan menunggu validasi. Tunggu sebagian divalidasi atau batalkan salah satunya.']);

    expect(Report::count())->toBe(5);
});

test('4 pending + 1 cancelled allows 5th submission', function () {
    Report::factory()->pending()->count(4)->for($this->reporter, 'user')->create();
    Report::factory()->cancelled()->for($this->reporter, 'user')->create();

    $this->actingAs($this->reporter)->post(route('reports.store'), ReportInput::valid())
        ->assertSessionHasNoErrors();

    expect(Report::where('user_id', $this->reporter->id)->count())->toBe(6);
});

test('only pending status counts toward the 5-report limit, not cancelled or rejected', function () {
    Report::factory()->pending()->count(3)->for($this->reporter, 'user')->create();
    Report::factory()->cancelled()->for($this->reporter, 'user')->create();
    Report::factory()->rejected()->for($this->reporter, 'user')->create();

    $this->actingAs($this->reporter)->post(route('reports.store'), ReportInput::valid())
        ->assertSessionHasNoErrors();

    expect(Report::where('user_id', $this->reporter->id)->count())->toBe(6);
});

test('11th report submission within an hour is throttled with Indonesian message', function () {
    $key = ReportSubmissionLimit::key($this->reporter);

    // Simulate 10 successful submissions by hitting the rate limiter
    for ($i = 0; $i < 10; $i++) {
        RateLimiter::hit($key, ReportSubmissionLimit::DECAY_SECONDS);
    }

    // 11th attempt should be throttled
    $response = $this->actingAs($this->reporter)->post(route('reports.store'), ReportInput::valid(['title' => str_repeat('x', 10)]));

    $response->assertSessionHasErrors(['title' => 'Terlalu banyak laporan dalam satu jam. Coba lagi nanti.']);

    expect(Report::where('user_id', $this->reporter->id)->count())->toBe(0);
});

test('invalid submissions do not consume rate limit quota', function () {
    // Try 10 invalid submissions (short title)
    for ($i = 0; $i < 10; $i++) {
        $this->actingAs($this->reporter)->post(route('reports.store'), ReportInput::valid(['title' => 'short']))
            ->assertSessionHasErrors(['title']);
    }

    expect(Report::where('user_id', $this->reporter->id)->count())->toBe(0);

    // Valid submission on 11th attempt should still succeed
    $this->actingAs($this->reporter)->post(route('reports.store'), ReportInput::valid(['title' => str_repeat('x', 10)]))
        ->assertSessionHasNoErrors();

    expect(Report::where('user_id', $this->reporter->id)->count())->toBe(1);
});

test('10 successful real submissions then 11th is throttled', function () {
    // First 5 submissions succeed (stores pending reports)
    for ($i = 0; $i < 5; $i++) {
        $this->actingAs($this->reporter)->post(route('reports.store'), ReportInput::valid(['title' => 'Report number '.($i + 1)]))
            ->assertSessionHasNoErrors();
    }

    expect(Report::where('user_id', $this->reporter->id)->where('status', ReportStatus::Pending)->count())->toBe(5);

    // Cancel the 5 reports to free up slots for more submissions
    $reports = Report::where('user_id', $this->reporter->id)->get();
    foreach ($reports as $report) {
        $report->update(['status' => ReportStatus::Cancelled]);
    }

    // Submit 5 more (total 10)
    for ($i = 5; $i < 10; $i++) {
        $this->actingAs($this->reporter)->post(route('reports.store'), ReportInput::valid(['title' => 'Report number '.($i + 1)]))
            ->assertSessionHasNoErrors();
    }

    expect(Report::where('user_id', $this->reporter->id)->count())->toBe(10);

    // 11th attempt should be throttled
    $this->actingAs($this->reporter)->post(route('reports.store'), ReportInput::valid(['title' => 'Report number 11']))
        ->assertSessionHasErrors(['title' => 'Terlalu banyak laporan dalam satu jam. Coba lagi nanti.']);

    expect(Report::where('user_id', $this->reporter->id)->count())->toBe(10);
});

test('rate limit uses hourly decay not minute-level decay', function () {
    $key = ReportSubmissionLimit::key($this->reporter);

    // One successful submission
    $this->actingAs($this->reporter)->post(route('reports.store'), ReportInput::valid(['title' => str_repeat('x', 10)]))
        ->assertSessionHasNoErrors();

    // After one submission, availableIn should be > 60 seconds (hourly, not minute-level)
    $availableIn = RateLimiter::availableIn($key);
    expect($availableIn)->toBeGreaterThan(60);
});
