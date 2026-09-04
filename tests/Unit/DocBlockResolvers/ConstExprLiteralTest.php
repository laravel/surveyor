<?php

use Laravel\Surveyor\Analysis\Scope;
use Laravel\Surveyor\Parser\DocBlockParser;
use Laravel\Surveyor\Types\FloatType;
use Laravel\Surveyor\Types\IntType;

beforeEach(function () {
    $this->parser = app(DocBlockParser::class);
    $this->parser->setScope(new Scope);
});

it('resolves integer literals in every PHP notation', function (string $literal, int $expected) {
    [$type] = $this->parser->parseReturn("/**\n * @return {$literal}\n */");

    expect($type)->toBeInstanceOf(IntType::class);
    expect($type->value)->toBe($expected);
})->with([
    ['1000', 1000],
    ['-5', -5],
    ['1_000', 1000],
    ['0x1A', 26],
    ['0b101', 5],
    ['017', 15],
]);

it('resolves float literals', function (string $literal, float $expected) {
    [$type] = $this->parser->parseReturn("/**\n * @return {$literal}\n */");

    expect($type)->toBeInstanceOf(FloatType::class);
    expect($type->value)->toBe($expected);
})->with([
    ['1.5', 1.5],
    ['-0.25', -0.25],
    ['1_000.5', 1000.5],
]);
