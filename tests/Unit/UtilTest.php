<?php

use Laravel\Surveyor\Support\Util;

describe('Util::isClassOrInterface', function () {
    it('returns false for PHP built-in constants', function (string $constant) {
        expect(Util::isClassOrInterface($constant))->toBeFalse();
    })->with([
        'NULL',
        'TRUE',
        'FALSE',
        'INF',
        'NAN',
        'null',
        'true',
        'false',
        'PHP_INT_MAX',
        'PHP_EOL',
        'DIRECTORY_SEPARATOR',
        'STDIN',
    ]);

    it('returns true for real classes', function () {
        expect(Util::isClassOrInterface(\stdClass::class))->toBeTrue();
        expect(Util::isClassOrInterface(\Iterator::class))->toBeTrue();
    });
});

describe('Util::resolveClass', function () {
    it('returns the value unchanged for PHP built-in constants', function (string $constant) {
        expect(Util::resolveClass($constant))->toBe($constant);
    })->with([
        'NULL',
        'TRUE',
        'FALSE',
    ]);

    it('does not throw ReflectionException for non-existent classes', function () {
        expect(Util::resolveClass('NonExistentClassName'))->toBe('NonExistentClassName');
    });
});
