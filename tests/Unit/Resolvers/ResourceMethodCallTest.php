<?php

use App\Http\Resources\MethodCallResource;
use App\Support\ResourceMethodCalls;
use Laravel\Surveyor\Analyzer\AnalyzedCache;
use Laravel\Surveyor\Analyzer\Analyzer;
use Laravel\Surveyor\Types\Entities\ResourceResponse;

uses()->group('integration');

beforeEach(fn () => AnalyzedCache::clear());
afterEach(fn () => AnalyzedCache::clear());

it('preserves a resource collection shape through a method call', function (string $method) {
    $type = app(Analyzer::class)->analyzeClass(ResourceMethodCalls::class)->result()->getMethod($method)->returnType();

    expect($type)->toBeInstanceOf(ResourceResponse::class)
        ->and($type->resourceClass)->toBe(MethodCallResource::class)
        ->and($type->isCollection)->toBeTrue()
        ->and($type->data->keys())->toBe(['id', 'label']);
})->with(['before', 'after', 'external', 'chained', 'staticCall', 'recursiveCall']);

it('preserves resource collection shapes within an array returned by a method', function () {
    $type = app(Analyzer::class)->analyzeClass(ResourceMethodCalls::class)->result()->getMethod('groups')->returnType();

    expect($type->value['items'])->toBeInstanceOf(ResourceResponse::class)
        ->and($type->value['items']->resourceClass)->toBe(MethodCallResource::class);
});

it('preserves a nullable resource collection returned by a method', function () {
    $type = app(Analyzer::class)->analyzeClass(ResourceMethodCalls::class)->result()->getMethod('nullableCall')->returnType();

    expect($type)->toBeInstanceOf(ResourceResponse::class)
        ->and($type->isCollection)->toBeTrue()
        ->and($type->isNullable())->toBeTrue()
        ->and($type->data->keys())->toBe(['id', 'label']);
});
