<?php

test('validation messages are Indonesian', function () {
    expect(trans('validation.required', ['attribute' => 'judul']))
        ->toBe('Judul wajib diisi.');
});

test('failed login message is Indonesian', function () {
    expect(trans('auth.failed'))->toBe('NIM/NIP atau password salah.');
});

test('the application runs in the Jakarta timezone', function () {
    expect(config('app.timezone'))->toBe('Asia/Jakarta');
});
