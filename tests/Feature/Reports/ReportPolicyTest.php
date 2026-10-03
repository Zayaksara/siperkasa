<?php

use App\Enums\ReportStatus as S;
use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

const REPORT_POLICY_ABILITIES = ['view', 'update', 'cancel', 'validate', 'reject', 'reassign', 'start', 'hold', 'resume', 'complete'];

/** @return list<string> */
function reportPolicyExpectedAbilities(string $actor, S $status, bool $assigned): array
{
    $pending = $status === S::Pending;

    return array_values(array_filter(match ($actor) {
        'owner' => ['view', $pending ? 'update' : null, $pending ? 'cancel' : null],
        'admin' => [
            'view',
            $pending ? 'validate' : null,
            $pending ? 'reject' : null,
            in_array($status, [S::Assigned, S::InProgress, S::OnHold], true) ? 'reassign' : null,
        ],
        'assigned_pic' => $assigned ? [
            'view',
            $status === S::Assigned ? 'start' : null,
            $status === S::InProgress ? 'hold' : null,
            $status === S::OnHold ? 'resume' : null,
            $status === S::InProgress ? 'complete' : null,
        ] : [],
        default => [], // other_user, other_pic
    }));
}

$matrix = [];
foreach (['owner', 'other_user', 'admin', 'assigned_pic', 'other_pic'] as $actor) {
    foreach (S::cases() as $status) {
        $matrix["{$actor} / {$status->value}"] = [$actor, $status];
    }
}

it('grants exactly the right abilities', function (string $actor, S $status) {
    $owner = User::factory()->create();
    $pic = User::factory()->pic()->create();
    $assigned = in_array($status, [S::Assigned, S::InProgress, S::OnHold, S::Closed], true);

    $report = Report::factory()->for($owner)->create([
        'status' => $status,
        'assigned_pic_id' => $assigned ? $pic->id : null,
    ]);

    $user = match ($actor) {
        'owner' => $owner,
        'other_user' => User::factory()->create(),
        'admin' => User::factory()->admin()->create(),
        'assigned_pic' => $pic,
        'other_pic' => User::factory()->pic()->create(),
    };

    $expected = reportPolicyExpectedAbilities($actor, $status, $assigned);

    foreach (REPORT_POLICY_ABILITIES as $ability) {
        expect(Gate::forUser($user)->allows($ability, $report))
            ->toBe(in_array($ability, $expected, true), "{$actor} / {$status->value} / {$ability}");
    }
})->with($matrix);

it('only reporters can create reports', function () {
    expect(Gate::forUser(User::factory()->create())->allows('create', Report::class))->toBeTrue()
        ->and(Gate::forUser(User::factory()->admin()->create())->allows('create', Report::class))->toBeFalse()
        ->and(Gate::forUser(User::factory()->pic()->create())->allows('create', Report::class))->toBeFalse();
});

it('lets every role open the report lists', function () {
    foreach (['reporter', 'admin', 'pic'] as $state) {
        expect(Gate::forUser(User::factory()->{$state}()->create())->allows('viewAny', Report::class))->toBeTrue();
    }
});
