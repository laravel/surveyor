<?php

use Laravel\Surveyor\Analyzer\AnalyzedCache;
use Laravel\Surveyor\Analyzer\Analyzer;
use Laravel\Surveyor\Types\BoolType;
use Laravel\Surveyor\Types\Entities\JsonEncoded;
use Laravel\Surveyor\Types\UnionType;

beforeEach(fn () => AnalyzedCache::clear());
afterEach(fn () => AnalyzedCache::clear());

it('preserves json_encode failure returns according to its flags', function (string $expression, bool $canFail) {
    $fixture = createPhpFixture('
namespace App;

class JsonEncoding
{
    public function encode(int $flags)
    {
        return '.$expression.';
    }
}');

    try {
        $type = app(Analyzer::class)->analyze($fixture)->result()->getMethod('encode')->returnType();

        if ($canFail) {
            expect($type)->toBeInstanceOf(UnionType::class)
                ->and($type->types[0])->toBeInstanceOf(JsonEncoded::class)
                ->and($type->types[1])->toBeInstanceOf(BoolType::class)
                ->and($type->types[1]->value)->toBeFalse();
        } else {
            expect($type)->toBeInstanceOf(JsonEncoded::class);
        }
    } finally {
        unlink($fixture);
    }
})->with([
    'default flags' => ['json_encode(NAN)', true],
    'unknown flags' => ['json_encode(NAN, $flags)', true],
    'numeric conversion' => ['json_encode(NAN, JSON_NUMERIC_CHECK)', true],
    'throwing' => ['json_encode(NAN, JSON_THROW_ON_ERROR)', false],
    'throwing with numeric conversion' => ['json_encode(NAN, JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR)', false],
    'named throwing flags' => ['json_encode(flags: JSON_THROW_ON_ERROR, value: NAN)', false],
]);

it('does not treat a first-class json_encode() callable as encoded data', function () {
    $fixture = createPhpFixture('
namespace App;

class JsonEncoder
{
    public function encoder()
    {
        return json_encode(...);
    }
}');

    try {
        $type = app(Analyzer::class)->analyze($fixture)->result()->getMethod('encoder')->returnType();

        $types = $type instanceof UnionType ? $type->types : [$type];

        expect(array_filter($types, fn ($member) => $member instanceof JsonEncoded))->toBeEmpty();
    } finally {
        unlink($fixture);
    }
});
