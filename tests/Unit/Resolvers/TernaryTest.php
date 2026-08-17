<?php

use Laravel\Surveyor\Analyzer\AnalyzedCache;
use Laravel\Surveyor\Analyzer\Analyzer;
use Laravel\Surveyor\Types\ArrayType;
use Laravel\Surveyor\Types\IntType;
use Laravel\Surveyor\Types\StringType;
use Laravel\Surveyor\Types\UnionType;

uses()->group('integration');

beforeEach(function () {
    AnalyzedCache::clear();

    app()->forgetInstance(Analyzer::class);
});

afterEach(function () {
    AnalyzedCache::clear();
});

function ternaryReturnType(string $body)
{
    $fixture = createPhpFixture('
namespace App\\Test;

class TernarySubject
{
    public function target(string $input)
    {
        '.$body.'
    }
}');

    $result = app(Analyzer::class)->analyze($fixture)->result();

    unlink($fixture);

    return $result->getMethod('target')->returnType();
}

describe('Ternary resolver', function () {
    it('returns the types of the branches, not of the compared value', function () {
        $type = ternaryReturnType("return \$input === 'request' ? 1 : 2;");

        $types = $type instanceof UnionType ? $type->types : [$type];

        foreach ($types as $inner) {
            expect($inner)->toBeInstanceOf(IntType::class);
        }
    });

    it('does not leak a compared literal that names a loadable class', function () {
        // `Request` exists case-insensitively, so a leaked `request` literal
        // resolves to a class and reaches generated output as a fake response.
        $type = ternaryReturnType("return \$input === 'request' ? 'yes' : 'no';");

        $types = $type instanceof UnionType ? $type->types : [$type];

        expect(array_map(fn ($inner) => $inner->id(), $types))
            ->not->toContain('request');

        foreach ($types as $inner) {
            expect($inner)->toBeInstanceOf(StringType::class);
        }
    });

    it('keeps the compared value usable as a branch', function () {
        $type = ternaryReturnType("return \$input === 'yes' ? \$input : 'no';");

        $types = $type instanceof UnionType ? $type->types : [$type];

        foreach ($types as $inner) {
            expect($inner)->toBeInstanceOf(StringType::class);
        }
    });

    it('gives a truthiness check the shape of its branches', function () {
        // The shape Laravel controllers use constantly: build an array when a
        // value is present, null when it is not. The array shape is what the
        // key carries, not the type of the value under test.
        $type = ternaryReturnType("return \$input ? ['name' => 'a', 'size' => 1] : null;");

        $types = $type instanceof UnionType ? $type->types : [$type];

        $shapes = array_values(array_filter($types, fn ($inner) => $inner instanceof ArrayType));

        expect($shapes)->toHaveCount(1);
        expect(array_keys($shapes[0]->value))->toBe(['name', 'size']);
    });

    it('resolves both sides of a nested ternary', function () {
        $type = ternaryReturnType("return \$input === 'a' ? 1 : (\$input === 'b' ? 2 : 3);");

        $types = $type instanceof UnionType ? $type->types : [$type];

        foreach ($types as $inner) {
            expect($inner)->toBeInstanceOf(IntType::class);
        }
    });

    it('still resolves the short form to its one branch', function () {
        $type = ternaryReturnType("return \$input ?: 'fallback';");

        $types = $type instanceof UnionType ? $type->types : [$type];

        foreach ($types as $inner) {
            expect($inner)->toBeInstanceOf(StringType::class);
        }
    });
});
