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

function matchReturnType(string $body)
{
    $fixture = createPhpFixture('
namespace App\Test;

class MatchSubject
{
    public function target()
    {
        '.$body.'
    }
}');

    $result = app(Analyzer::class)->analyze($fixture)->result();

    unlink($fixture);

    return $result->getMethod('target')->returnType();
}

describe('Match resolver', function () {
    it('returns the types of the arm bodies', function () {
        $type = matchReturnType('
            $value = true;

            return match (true) {
                $value => ["yes"],
                default => [],
            };
        ');

        expect($type)->toBeInstanceOf(ArrayType::class);
    });

    it('includes the default arm type', function () {
        $type = matchReturnType('
            return match (true) {
                true => 1,
                default => "fallback",
            };
        ');

        expect($type)->toBeInstanceOf(UnionType::class);
        expect($type->types)->toHaveCount(2);
        expect($type->types[0])->toBeInstanceOf(IntType::class);
        expect($type->types[1])->toBeInstanceOf(StringType::class);
    });

    it('resolves a match with only a default arm', function () {
        $type = matchReturnType('return match (true) { default => "fallback" };');

        expect($type)->toBeInstanceOf(StringType::class);
        expect($type->value)->toBe('fallback');
    });

    it('resolves an arm with multiple conditions', function () {
        $type = matchReturnType('return match (1) { 1, 2 => "value" };');

        expect($type)->toBeInstanceOf(StringType::class);
        expect($type->value)->toBe('value');
    });
});
