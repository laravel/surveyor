<?php

use App\DTOs\PriceDTO;
use App\DTOs\ScalarJsonDTO;
use Laravel\Surveyor\Analyzer\AnalyzedCache;
use Laravel\Surveyor\Analyzer\ArrayableResolver;
use Laravel\Surveyor\Types\ArrayType;
use Laravel\Surveyor\Types\ClassType;
use Laravel\Surveyor\Types\StringType;

beforeEach(fn () => AnalyzedCache::clear());
afterEach(fn () => AnalyzedCache::clear());

it('resolves a scalar JsonSerializable result', function () {
    $type = app(ArrayableResolver::class)->resolve(new ClassType(ScalarJsonDTO::class));

    expect($type)->toBeInstanceOf(StringType::class);
});

it('continues to resolve array JsonSerializable results', function () {
    $type = app(ArrayableResolver::class)->resolve(new ClassType(PriceDTO::class));

    expect($type)->toBeInstanceOf(ArrayType::class)
        ->and($type->keys())->toContain('amount', 'currency', 'currency_amount');
});
