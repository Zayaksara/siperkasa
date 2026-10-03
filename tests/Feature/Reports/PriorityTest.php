<?php

use App\Enums\Priority;

test('default SLA days per priority come from config', function () {
    expect(Priority::Critical->slaDays())->toBe(1)
        ->and(Priority::High->slaDays())->toBe(3)
        ->and(Priority::Medium->slaDays())->toBe(7)
        ->and(Priority::Low->slaDays())->toBe(14);
});

test('priorities have Indonesian labels', function () {
    expect(Priority::Critical->label())->toBe('Kritis');
});
