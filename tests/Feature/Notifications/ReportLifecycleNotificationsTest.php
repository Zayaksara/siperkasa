<?php

use App\Models\Category;
use App\Models\Report;
use App\Models\Room;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('every step of a report notifies the right people with the right link', function () {
    Storage::fake('local');

    $reporter = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $otherAdmin = User::factory()->admin()->inactive()->create();
    $pic = User::factory()->pic()->create();
    $unrelatedPic = User::factory()->pic()->create();

    $unread = fn (User $user) => $user->unreadNotifications()->count();

    // 1. the reporter files a report → active admins are told
    $this->actingAs($reporter)->post(route('reports.store'), [
        'title' => 'Proyektor mati',
        'category_id' => Category::factory()->create()->id,
        'room_id' => Room::factory()->create()->id,
        'description' => 'Proyektor ruang 101 tidak menyala.',
        'user_priority' => 'High',
        'photos' => [UploadedFile::fake()->image('a.jpg')],
    ])->assertSessionHasNoErrors();

    $report = Report::sole();

    expect($unread($admin))->toBe(1)
        ->and($unread($otherAdmin))->toBe(0)
        ->and($unread($reporter))->toBe(0);
    expect($admin->notifications()->first()->data['url'])->toBe(route('admin.reports.show', $report, false));
    expect($admin->notifications()->first()->data['message'])->toContain($report->ticket_number);

    // 2. the admin validates → the pic and the reporter are told
    $this->actingAs($admin)->post(route('admin.reports.approve', $report), [
        'admin_priority' => 'High',
        'sla_date' => now()->addDays(3)->format('Y-m-d\TH:i'),
        'assigned_pic_id' => $pic->id,
    ])->assertSessionHasNoErrors();

    expect($unread($pic))->toBe(1)->and($unread($reporter))->toBe(1)->and($unread($unrelatedPic))->toBe(0);
    expect($pic->notifications()->first()->data['url'])->toBe(route('pic.tasks.show', $report, false));

    // 3. the pic works on it → only the reporter is told
    $this->actingAs($pic)->post(route('pic.tasks.start', $report));
    $this->actingAs($pic)->post(route('pic.tasks.hold', $report), ['hold_reason' => 'Menunggu sparepart datang.']);
    $this->actingAs($pic)->post(route('pic.tasks.resume', $report));
    $this->actingAs($pic)->post(route('pic.tasks.complete', $report), [
        'repair_action' => 'Mengganti lampu proyektor.',
        'completed_on' => now()->toDateString(),
        'photos' => [UploadedFile::fake()->image('selesai.jpg')],
    ])->assertSessionHasNoErrors();

    expect($unread($reporter))->toBe(5)   // assigned, started, held, resumed, closed
        ->and($unread($pic))->toBe(1)
        ->and($unread($admin))->toBe(1);

    // notifications created within one second share a timestamp, so pick the closing one by status
    $latest = $reporter->notifications->first(fn ($n) => $n->data['status'] === 'Closed');
    expect($latest)->not->toBeNull()
        ->and($latest->data['url'])->toBe(route('reports.show', $report, false))
        ->and($latest->data['message'])->toBe("Laporan {$report->ticket_number} sudah selesai.");
});
