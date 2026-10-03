<?php

use App\Support\Bmn\BmnValue;

test('numeric codes become clean strings', function () {
    expect(BmnValue::string(3100101001))->toBe('3100101001');
    expect(BmnValue::string(3100101001.0))->toBe('3100101001');
    expect(BmnValue::string('  ABC '))->toBe('ABC');
    expect(BmnValue::string(''))->toBeNull();
    expect(BmnValue::string(null))->toBeNull();
});

test('nup accepts ints, integral floats and numeric strings', function () {
    expect(BmnValue::int(1))->toBe(1);
    expect(BmnValue::int(1.0))->toBe(1);
    expect(BmnValue::int('12'))->toBe(12);
    expect(BmnValue::int('abc'))->toBeNull();
    expect(BmnValue::int(null))->toBeNull();
});

test('excel serial dates convert to ISO dates', function () {
    expect(BmnValue::date(39063))->toBe('2006-12-12');
    expect(BmnValue::date(42510))->toBe('2016-05-20');
    expect(BmnValue::date(39063.0))->toBe('2006-12-12');
});

test('the 9999-12-31 placeholder and blanks become null', function () {
    expect(BmnValue::date(2958465))->toBeNull();
    expect(BmnValue::date(0))->toBeNull();
    expect(BmnValue::date(null))->toBeNull();
    expect(BmnValue::date(''))->toBeNull();
    expect(BmnValue::date(new DateTimeImmutable('9999-12-31')))->toBeNull();
});

test('real date objects and ISO strings pass through', function () {
    expect(BmnValue::date(new DateTimeImmutable('2020-03-04 10:00:00')))->toBe('2020-03-04');
    expect(BmnValue::date('2020-03-04'))->toBe('2020-03-04');
    expect(BmnValue::date('not a date'))->toBeNull();
});

test('decimals are normalised to two places', function () {
    expect(BmnValue::decimal(1500000))->toBe('1500000.00');
    expect(BmnValue::decimal('1500000.5'))->toBe('1500000.50');
    expect(BmnValue::decimal(null))->toBeNull();
    expect(BmnValue::decimal('x'))->toBeNull();
});

test('json values keep numbers but turn dates and blanks into plain values', function () {
    expect(BmnValue::json(new DateTimeImmutable('2020-03-04')))->toBe('2020-03-04');
    expect(BmnValue::json(' '))->toBeNull();
    expect(BmnValue::json(2958465))->toBe(2958465);
    expect(BmnValue::json(5.0))->toBe(5);
});
