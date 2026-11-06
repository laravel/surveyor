<?php

use Laravel\Surveyor\Analysis\Scope;
use Laravel\Surveyor\Parser\DocBlockParser;
use Laravel\Surveyor\Types\ArrayShapeType;
use Laravel\Surveyor\Types\BoolType;
use Laravel\Surveyor\Types\Contracts\Type;
use Laravel\Surveyor\Types\FloatType;
use Laravel\Surveyor\Types\IntType;
use Laravel\Surveyor\Types\MixedType;
use Laravel\Surveyor\Types\NeverType;
use Laravel\Surveyor\Types\NumberType;
use Laravel\Surveyor\Types\StringType;
use Laravel\Surveyor\Types\Type as SurveyorType;
use Laravel\Surveyor\Types\UnionType;

beforeEach(function () {
    $this->parser = app(DocBlockParser::class);
    $this->parser->setScope(new Scope);
});

it('resolves array-key as integer or string', function () {
    [$type] = $this->parser->parseReturn('/** @return array-key */');

    expect($type)->toEqual(new UnionType([new IntType, new StringType]));
});

it('resolves array-key in generic arrays and template bounds', function (string $keyType) {
    $docBlock = "/**\n * @template TKey of array-key\n * @return array<{$keyType}, mixed>\n */";
    $this->parser->parseTemplateTags($docBlock);

    [$type] = $this->parser->parseReturn($docBlock);

    expect($type)->toEqual(new ArrayShapeType(
        new UnionType([new IntType, new StringType]),
        new MixedType,
    ));
})->with(['array-key', 'TKey']);

it('preserves a quoted array-key string literal', function () {
    [$type] = $this->parser->parseReturn("/** @return 'array-key' */");

    expect($type)->toEqual(new StringType('array-key'));
});

it('resolves pseudo-types to the types they describe', function (string $docType, Type $expected) {
    [$type] = $this->parser->parseReturn("/** @return {$docType} */");

    expect($type)->toEqual($expected);
})->with([
    'positive-int' => ['positive-int', new IntType],
    'non-negative-int' => ['non-negative-int', new IntType],
    'non-zero-int' => ['non-zero-int', new IntType],
    'non-empty-string' => ['non-empty-string', new StringType],
    'numeric-string' => ['numeric-string', new StringType],
    'lowercase-string' => ['lowercase-string', new StringType],
    'class-string' => ['class-string', new StringType],
    'double' => ['double', new FloatType],
    'boolean' => ['boolean', new BoolType],
    'number' => ['number', new NumberType],
    'numeric' => ['numeric', new UnionType([new IntType, new FloatType, new StringType])],
    'scalar' => ['scalar', new UnionType([new IntType, new FloatType, new StringType, new BoolType])],
    'list' => ['list', new ArrayShapeType(new IntType, new MixedType)],
    'non-empty-list' => ['non-empty-list', new ArrayShapeType(new IntType, new MixedType)],
    'non-empty-array' => ['non-empty-array', SurveyorType::from('array')],
    'never' => ['never', new NeverType],
    'no-return' => ['no-return', new NeverType],
]);

it('resolves unmodelled hyphenated pseudo-types to mixed', function () {
    [$type] = $this->parser->parseReturn('/** @return callable-array */');

    expect($type)->toEqual(new MixedType);
});

it('preserves quoted pseudo-type string literals', function () {
    [$type] = $this->parser->parseReturn("/** @return 'positive-int'|'scalar' */");

    expect($type)->toEqual(new UnionType([new StringType('positive-int'), new StringType('scalar')]));
});

it('resolves pseudo-types inside generic arrays', function () {
    [$type] = $this->parser->parseReturn('/** @return array<non-empty-string, positive-int> */');

    expect($type)->toEqual(new ArrayShapeType(new StringType, new IntType));
});

it('lets a template named like a pseudo-type win', function () {
    $docBlock = "/**\n * @template scalar of string\n * @return scalar\n */";
    $this->parser->parseTemplateTags($docBlock);

    [$type] = $this->parser->parseReturn($docBlock);

    expect($type)->toEqual(new StringType);
});
