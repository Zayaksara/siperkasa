<?php

use App\Actions\Reports\InvalidTransition;
use App\Actions\Reports\ValidateReport;
use App\Enums\Priority;
use App\Enums\ReportStatus;
use App\Models\Category;
use App\Models\Report;
use App\Models\User;
use App\Notifications\ReportAssigned;
use App\Notifications\ReportStatusChanged;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->admin = User::factory()->admin()->create();
    $this->pic = User::factory()->pic()->create();
    $this->report = Report::factory()->create(['user_priority' => Priority::Medium]);
});

function adminApprovePayload(User $pic, array $overrides = []): array
{
    return array_merge([
        'admin_priority' => 'High',
        'sla_date' => now()->addDays(3)->format('Y-m-d\TH:i'),
        'assigned_pic_id' => $pic->id,
    ], $overrides);
}

test('the detail page proposes the SLA per priority and the category default pic', function () {
    $this->travelTo(Carbon::parse('2026-10-15 10:00', 'Asia/Jakarta'));
    $category = Category::factory()->create(['default_pic_id' => $this->pic->id]);
    $report = Report::factory()->create(['category_id' => $category->id]);

    $this->actingAs($this->admin)->get(route('admin.reports.show', $report))
        ->assertInertia(fn ($page) => $page
            ->where('suggested_pic_id', $this->pic->id)
            ->where('sla_defaults.Critical', '2026-10-16T10:00')
            ->where('sla_defaults.High', '2026-10-18T10:00')
            ->where('sla_defaults.Medium', '2026-10-22T10:00')
            ->where('sla_defaults.Low', '2026-10-29T10:00')
            ->has('pics', 1));
});

test('an inactive default pic is not suggested and not offered', function () {
    $inactive = User::factory()->pic()->inactive()->create();
    $category = Category::factory()->create(['default_pic_id' => $inactive->id]);
    $report = Report::factory()->create(['category_id' => $category->id]);

    $this->actingAs($this->admin)->get(route('admin.reports.show', $report))
        ->assertInertia(fn ($page) => $page->where('suggested_pic_id', null)->has('pics', 1));
});

test('validating assigns the report and records everything', function () {
    $this->travelTo(Carbon::parse('2026-10-15 10:00', 'Asia/Jakarta'));

    $this->actingAs($this->admin)->post(route('admin.reports.approve', $this->report), adminApprovePayload($this->pic))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.reports.show', $this->report));

    $report = $this->report->fresh();
    expect($report->status)->toBe(ReportStatus::Assigned)
        ->and($report->admin_id)->toBe($this->admin->id)
        ->and($report->admin_priority)->toBe(Priority::High)
        ->and($report->assigned_pic_id)->toBe($this->pic->id)
        ->and($report->sla_date->format('Y-m-d H:i'))->toBe('2026-10-18 10:00')
        ->and($report->validated_at)->not->toBeNull();

    $log = $report->statusLogs()->sole();
    expect($log->from_status)->toBe('Pending')->and($log->to_status)->toBe('Assigned')->and($log->actor_id)->toBe($this->admin->id);

    Notification::assertSentTo($this->pic, ReportAssigned::class);
    Notification::assertSentTo($report->user, ReportStatusChanged::class, fn ($n) => $n->report->status === ReportStatus::Assigned);
});

test('required fields and rules are validated', function () {
    $this->actingAs($this->admin)->post(route('admin.reports.approve', $this->report), [])
        ->assertSessionHasErrors(['admin_priority', 'sla_date', 'assigned_pic_id']);

    expect($this->report->fresh()->status)->toBe(ReportStatus::Pending);
});

test('the SLA must be in the future', function () {
    $this->actingAs($this->admin)->post(route('admin.reports.approve', $this->report), adminApprovePayload($this->pic, ['sla_date' => now()->subDay()->format('Y-m-d\TH:i')]))
        ->assertSessionHasErrors('sla_date');

    $this->actingAs($this->admin)->post(route('admin.reports.approve', $this->report), adminApprovePayload($this->pic, ['sla_date' => now()->format('Y-m-d\TH:i')]))
        ->assertSessionHasErrors('sla_date');

    expect($this->report->fresh()->status)->toBe(ReportStatus::Pending);
});

test('the assignee must be an active pic', function (string $state, bool $active) {
    $user = User::factory()->{$state}()->create(['is_active' => $active]);

    $this->actingAs($this->admin)->post(route('admin.reports.approve', $this->report), adminApprovePayload($user))
        ->assertSessionHasErrors('assigned_pic_id');

    expect($this->report->fresh()->status)->toBe(ReportStatus::Pending);
})->with([['reporter', true], ['admin', true], ['pic', false]]);

test('a soft-deleted pic is not accepted', function () {
    $pic = User::factory()->pic()->create();
    $picId = $pic->id;
    $pic->delete();

    $this->actingAs($this->admin)->post(route('admin.reports.approve', $this->report), [
        'admin_priority' => 'High',
        'sla_date' => now()->addDays(3)->format('Y-m-d\TH:i'),
        'assigned_pic_id' => $picId,
    ])->assertSessionHasErrors('assigned_pic_id');

    expect($this->report->fresh()->status)->toBe(ReportStatus::Pending);
});

test('a second, stale approval is refused and keeps the first assignment', function () {
    $otherPic = User::factory()->pic()->create();

    $this->actingAs($this->admin)->post(route('admin.reports.approve', $this->report), adminApprovePayload($this->pic))->assertRedirect();
    $this->actingAs(User::factory()->admin()->create())->post(route('admin.reports.approve', $this->report), adminApprovePayload($otherPic, ['admin_priority' => 'Low']))->assertForbidden();

    $report = $this->report->fresh();
    expect($report->assigned_pic_id)->toBe($this->pic->id)
        ->and($report->admin_priority)->toBe(Priority::High)
        ->and($report->statusLogs()->count())->toBe(1);
});

test('a stale validate is refused and changes nothing', function () {
    $this->travelTo(Carbon::parse('2026-10-15 10:00', 'Asia/Jakarta'));

    Notification::fake();
    $report = Report::factory()->create(['status' => ReportStatus::Pending]);
    Report::whereKey($report->id)->update(['status' => ReportStatus::Assigned]); // row moved on, model stale

    expect(fn () => app(ValidateReport::class)->handle(
        $report,
        $this->admin,
        Priority::High,
        now()->addDays(3),
        $this->pic,
    ))->toThrow(InvalidTransition::class);

    expect($report->fresh()->status)->toBe(ReportStatus::Assigned)
        ->and($report->statusLogs()->count())->toBe(0);
    Notification::assertNothingSent();
});

test('only admins can validate', function (string $state) {
    $this->actingAs(User::factory()->{$state}()->create())->post(route('admin.reports.approve', $this->report), adminApprovePayload($this->pic))->assertForbidden();
})->with(['reporter', 'pic']);
