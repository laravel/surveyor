<?php

use Laravel\Surveyor\Analyzer\AnalyzedCache;
use Laravel\Surveyor\Analyzer\Analyzer;
use Laravel\Surveyor\Types\IntType;
use Laravel\Surveyor\Types\UnionType;

uses()->group('integration');

beforeEach(function () {
    AnalyzedCache::clear();

    app()->forgetInstance(Analyzer::class);
});

afterEach(function () {
    AnalyzedCache::clear();
});

it('does not narrow a variable to the type a condition ruled out', function () {
    $fixture = createPhpFixture('
namespace App\Test;

class NarrowingSubject
{
    public function target(bool $flag)
    {
        $value = "hi";

        if (! is_int($value) && $flag) {
            return $value;
        }

        return 1.5;
    }
}');

    $type = app(Analyzer::class)->analyze($fixture)->result()->getMethod('target')->returnType();

    unlink($fixture);

    $types = $type instanceof UnionType ? $type->types : [$type];

    expect($types)->each->not->toBeInstanceOf(IntType::class);
});
