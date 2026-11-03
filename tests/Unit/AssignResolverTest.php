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

describe('array keys', function () {
    it('keeps assigned keys and removes unset ones', function () {
        $fixture = createPhpFixture('
namespace App;

class ArrayKeyAssignTest
{
    public function test(bool $flag)
    {
        $array = ["keep" => 1, "drop" => "x", "meta" => ["a" => 1, "b" => 2]];
        $array["2fa_enabled"] = $flag;
        $array["meta"]["c"] = 3;
        unset($array["drop"], $array["meta"]["a"]);

        return $array;
    }
}
');

        $type = app(Analyzer::class)->analyze($fixture)->result()->getMethod('test')->returnType();

        expect(array_keys($type->value))->toBe(['keep', 'meta', '2fa_enabled']);
        expect(array_keys($type->value['meta']->value))->toBe(['b', 'c']);
    });
});
