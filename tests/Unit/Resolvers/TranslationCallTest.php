<?php

use Illuminate\Contracts\Translation\Translator;
use Laravel\Surveyor\Analyzer\AnalyzedCache;
use Laravel\Surveyor\Analyzer\Analyzer;
use Laravel\Surveyor\Types\ArrayShapeType;
use Laravel\Surveyor\Types\ClassType;
use Laravel\Surveyor\Types\MixedType;
use Laravel\Surveyor\Types\NullType;
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

        $names = array_map(fn ($member) => $member instanceof ClassType ? $member->resolved() : $member::class, $types);

        if (collect($types)->contains(fn ($member) => $member->isNullable())) {
            $names[] = NullType::class;
        }

        expect($names)
            ->toEqualCanonicalizing($expected);
    } finally {
        unlink($fixture);
    }
})->with([
    'literal key' => ['', 'trans("messages.greeting")', [ArrayShapeType::class, StringType::class]],
    'named key' => ['', 'trans(locale: "fr", key: "messages.greeting")', [ArrayShapeType::class, StringType::class]],
    'string key' => ['string $key', 'trans($key)', [ArrayShapeType::class, StringType::class]],
    'no key' => ['', 'trans()', [Translator::class]],
    'null key' => ['', 'trans(null)', [Translator::class]],
    'omitted named key' => ['', 'trans(locale: "fr")', [Translator::class]],
    'nullable key' => ['?string $key', 'trans($key)', [Translator::class, ArrayShapeType::class, StringType::class]],
    'unknown key' => ['mixed $key', 'trans($key)', [Translator::class, ArrayShapeType::class, StringType::class]],
    'unpacked arguments' => ['array $arguments', 'trans(...$arguments)', [Translator::class, ArrayShapeType::class, StringType::class]],
    '__ literal key' => ['', '__("messages.greeting")', [ArrayShapeType::class, StringType::class]],
    '__ named key' => ['', '__(locale: "fr", key: "messages.greeting")', [ArrayShapeType::class, StringType::class]],
    '__ string key' => ['string $key', '__($key)', [ArrayShapeType::class, StringType::class]],
    // Type::union() reads a lone null as mixed.
    '__ no key' => ['', '__()', [MixedType::class]],
    '__ null key' => ['', '__(null)', [MixedType::class]],
    '__ nullable key' => ['?string $key', '__($key)', [NullType::class, ArrayShapeType::class, StringType::class]],
    '__ unknown key' => ['mixed $key', '__($key)', [NullType::class, ArrayShapeType::class, StringType::class]],
    '__ unpacked arguments' => ['array $arguments', '__(...$arguments)', [NullType::class, ArrayShapeType::class, StringType::class]],
]);
