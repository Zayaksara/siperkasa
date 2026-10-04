<?php

test('the WebP logo exists and stays small', function () {
    $path = public_path('images/logo-uinssc.webp');

    expect(file_exists($path))->toBeTrue()
        ->and(filesize($path))->toBeLessThan(20 * 1024);
});

test('the PNG logo is kept for PDF export', function () {
    expect(file_exists(public_path('images/logo-uinssc.png')))->toBeTrue();
});
