<?php

use App\Models\Report;
use App\Support\TicketNumber;
use Illuminate\Support\Carbon;

test('the first ticket of a month ends in 0001 and the next increments', function () {
    $this->travelTo(Carbon::parse('2026-10-15 09:00', 'Asia/Jakarta'));

    expect(TicketNumber::next())->toBe('SPK-2610-0001');

    Report::factory()->create(['ticket_number' => 'SPK-2610-0001']);

    expect(TicketNumber::next())->toBe('SPK-2610-0002');
});

test('numbering restarts every month', function () {
    $this->travelTo(Carbon::parse('2026-10-31 23:00', 'Asia/Jakarta'));
    Report::factory()->create(['ticket_number' => 'SPK-2610-0007']);

    $this->travelTo(Carbon::parse('2026-11-01 08:00', 'Asia/Jakarta'));

    expect(TicketNumber::next())->toBe('SPK-2611-0001');
});

test('five reports in a row get unique increasing tickets', function () {
    $this->travelTo(Carbon::parse('2026-10-15 09:00', 'Asia/Jakarta'));

    $tickets = [];

    foreach (range(1, 5) as $ignored) {
        $tickets[] = $ticket = TicketNumber::next();
        Report::factory()->create(['ticket_number' => $ticket]);
    }

    expect($tickets)->toBe([
        'SPK-2610-0001', 'SPK-2610-0002', 'SPK-2610-0003', 'SPK-2610-0004', 'SPK-2610-0005',
    ]);
});

test('an explicit date can be passed', function () {
    expect(TicketNumber::next(Carbon::parse('2027-01-05')))->toBe('SPK-2701-0001');
});
