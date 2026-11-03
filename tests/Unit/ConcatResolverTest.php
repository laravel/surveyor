<?php

use Laravel\Surveyor\Analyzer\AnalyzedCache;
use Laravel\Surveyor\Analyzer\Analyzer;
use Laravel\Surveyor\Types\StringType;

uses()->group('integration');

beforeEach(function () {
    AnalyzedCache::clear();
});

afterEach(function () {
    AnalyzedCache::clear();
});

function concatReturnType(string $body)
{
    $fixture = createPhpFixture('
namespace App;

class ConcatTest
{
    public function test(string $name)
    {
        '.$body.'
    }
}
');

    return app(Analyzer::class)->analyze($fixture)->result()->getMethod('test')->returnType();
}

it('joins two known strings into a literal', function () {
    expect(concatReturnType("return 'Modals/' . 'Edit';")->value)->toBe('Modals/Edit');
});

it('does not turn a partly unknown string into a literal', function () {
    expect(concatReturnType("return 'Modals/' . \$name;")->value)->toBeNull();
    expect(concatReturnType("return \$name . '/Edit';")->value)->toBeNull();

    $modal = concatReturnType("\$m = \\Illuminate\\Support\\Str::of(\$name)->studly()->toString(); return 'Modals/' . \$m;");

    expect($modal)->toBeInstanceOf(StringType::class);
    expect($modal->value)->toBeNull();
});
