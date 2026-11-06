<?php

use Laravel\Surveyor\Analysis\Scope;
use Laravel\Surveyor\Parser\DocBlockParser;
use Laravel\Surveyor\Types\ArrayShapeType;
use Laravel\Surveyor\Types\IntType;
use Laravel\Surveyor\Types\MixedType;
use Laravel\Surveyor\Types\StringType;
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
