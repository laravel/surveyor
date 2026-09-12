<?php

use Illuminate\Contracts\Translation\Translator;
use Laravel\Surveyor\Analyzer\AnalyzedCache;
use Laravel\Surveyor\Analyzer\Analyzer;
use Laravel\Surveyor\Types\ArrayType;
use Laravel\Surveyor\Types\ClassType;
use Laravel\Surveyor\Types\StringType;
use Laravel\Surveyor\Types\UnionType;

uses()->group('integration');

beforeEach(fn () => AnalyzedCache::clear());
afterEach(fn () => AnalyzedCache::clear());

it('resolves translation calls from their key argument', function (string $parameters, string $expression, array $expected) {
    $class = 'TranslationCalls'.md5($parameters.$expression);
    $fixture = createPhpFixture("
namespace App\\Test;

class {$class}
{
    public function label({$parameters})
    {
        return {$expression};
    }
}");

    try {
        require $fixture;

        $type = app(Analyzer::class)->analyze($fixture)->result()->getMethod('label')->returnType();
        $types = $type instanceof UnionType ? $type->types : [$type];

        expect(array_map(fn ($member) => $member instanceof ClassType ? $member->resolved() : $member::class, $types))
            ->toEqualCanonicalizing($expected);
    } finally {
        unlink($fixture);
    }
})->with([
    'literal key' => ['', 'trans("messages.greeting")', [ArrayType::class, StringType::class]],
    'named key' => ['', 'trans(locale: "fr", key: "messages.greeting")', [ArrayType::class, StringType::class]],
    'string key' => ['string $key', 'trans($key)', [ArrayType::class, StringType::class]],
    'no key' => ['', 'trans()', [Translator::class]],
    'null key' => ['', 'trans(null)', [Translator::class]],
    'omitted named key' => ['', 'trans(locale: "fr")', [Translator::class]],
    'nullable key' => ['?string $key', 'trans($key)', [Translator::class, ArrayType::class, StringType::class]],
    'unknown key' => ['mixed $key', 'trans($key)', [Translator::class, ArrayType::class, StringType::class]],
    'unpacked arguments' => ['array $arguments', 'trans(...$arguments)', [Translator::class, ArrayType::class, StringType::class]],
]);
