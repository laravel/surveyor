<?php

use Laravel\Surveyor\Analyzer\AnalyzedCache;
use Laravel\Surveyor\Analyzer\Analyzer;
use Laravel\Surveyor\Debug\Debug;
use Laravel\Surveyor\Types\ArrayType;
use Laravel\Surveyor\Types\FloatType;
use Laravel\Surveyor\Types\IntType;
use Laravel\Surveyor\Types\UnionType;

uses()->group('integration');

beforeEach(function () {
    AnalyzedCache::clear();
    Debug::$throw = true;
});

afterEach(function () {
    AnalyzedCache::clear();
    Debug::$throw = false;
});

function unaryMinusReturnType(string $expression)
{
    $fixture = createPhpFixture("
namespace App;

class UnaryMinusSubject
{
    public function test()
    {
        {$expression}
    }
}
");

    return app(Analyzer::class)
        ->analyze($fixture)
        ->result()
        ->getMethod('test')
        ->returnType();
}

it('negates an integer', function () {
    $returnType = unaryMinusReturnType('$x = 5; return -$x;');

    expect($returnType)->toBeInstanceOf(IntType::class);
    expect($returnType->value)->toBe(-5);
});

it('negates a float', function () {
    $returnType = unaryMinusReturnType('$x = 1.5; return -$x;');

    expect($returnType)->toBeInstanceOf(FloatType::class);
    expect($returnType->value)->toBe(-1.5);
});

it('negates the numeric members of a union and leaves the rest alone', function () {
    $returnType = unaryMinusReturnType('$x = rand(0, 1) ? [1, 2] : 5; return -$x;');

    expect($returnType)->toBeInstanceOf(UnionType::class);
    expect($returnType->types)->toHaveCount(2);
    expect($returnType->types[0])->toBeInstanceOf(ArrayType::class);
    expect($returnType->types[1])->toBeInstanceOf(IntType::class);
    expect($returnType->types[1]->value)->toBe(-5);
});
