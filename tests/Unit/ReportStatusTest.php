<?php

use App\Enums\ReportStatus as S;

$valid = [
    [S::Pending, S::Assigned],
    [S::Pending, S::Rejected],
    [S::Pending, S::Cancelled],
    [S::Assigned, S::InProgress],
    [S::InProgress, S::OnHold],
    [S::InProgress, S::Closed],
    [S::OnHold, S::InProgress],
];

it('allows every documented transition', function (S $from, S $to) {
    expect($from->canTransitionTo($to))->toBeTrue();
})->with($valid);

it('rejects every other pair, including staying in the same status', function () use ($valid) {
    $allowed = array_map(fn ($pair) => $pair[0]->value.'>'.$pair[1]->value, $valid);

    foreach (S::cases() as $from) {
        foreach (S::cases() as $to) {
            if (in_array($from->value.'>'.$to->value, $allowed, true)) {
                continue;
            }

            expect($from->canTransitionTo($to))->toBeFalse("{$from->value} → {$to->value} must be invalid");
        }
    }
});

it('knows its final statuses', function () {
    expect(S::Closed->isFinal())->toBeTrue()
        ->and(S::Rejected->isFinal())->toBeTrue()
        ->and(S::Cancelled->isFinal())->toBeTrue()
        ->and(S::Pending->isFinal())->toBeFalse()
        ->and(S::OnHold->isFinal())->toBeFalse();

    expect(S::finalValues())->toBe(['Closed', 'Rejected', 'Cancelled']);
});

it('exposes the database values and Indonesian labels', function () {
    expect(S::InProgress->value)->toBe('In_Progress')
        ->and(S::OnHold->value)->toBe('On_Hold')
        ->and(S::Closed->label())->toBe('Selesai');
});
