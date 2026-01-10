<?php

use Laravel\Surveyor\Analyzer\AnalyzedCache;
use Laravel\Surveyor\Analyzer\Analyzer;
use Laravel\Surveyor\Types\IntType;

uses()->group('integration');

beforeEach(function () {
    AnalyzedCache::clear();
});

afterEach(function () {
    AnalyzedCache::clear();
});

describe('array dim', function () {
    it('resolves array dim assignments', function () {
        $fixture = createPhpFixture('
namespace App;

class ArrayDimAssignTest
{
    public function test()
    {
        $a = [];
        
        return $a[] = 1;
    }
}
');

        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyze($fixture)->result();

        $method = $result->getMethod('test');
        $returnType = $method->returnType();

        expect($returnType)->toBeInstanceOf(IntType::class);
        expect($returnType->value)->toBe(1);
    });
});
