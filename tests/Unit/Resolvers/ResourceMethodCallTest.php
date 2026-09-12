<?php

use App\Http\Resources\CustomPayloadCollection;
use App\Http\Resources\MethodCallResource;
use App\Support\ResourceMethodCalls;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Laravel\Surveyor\Analyzer\AnalyzedCache;
use Laravel\Surveyor\Analyzer\Analyzer;
use Laravel\Surveyor\Types\Entities\ResourceResponse;
use Laravel\Surveyor\Types\Type;

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

it('preserves the anonymous receiver when collecting named collections through methods', function (string $method) {
    $resource = (new ResourceMethodCalls)->{$method}();

    expect($resource)->toBeInstanceOf(AnonymousResourceCollection::class)
        ->and($resource->resolve(Request::create('/')))->toBe([['count' => 42]]);

    $type = app(Analyzer::class)->analyzeClass(ResourceMethodCalls::class)->result()->getMethod($method)->returnType();

    expect($type)->toBeInstanceOf(ResourceResponse::class)
        ->and($type->resolved())->toBe(AnonymousResourceCollection::class)
        ->and($type->resourceClass)->toBe(CustomPayloadCollection::class)
        ->and($type->isCollection)->toBeTrue()
        ->and($type->data)->toEqual(Type::array(['count' => Type::int(42)]));
})->with(['collectedNamed', 'collectedNamedCall', 'collectedNamedFluent']);

it('resolves a collected named payload after a fluent method call', function () {
    $type = app(Analyzer::class)->analyzeClass(ResourceMethodCalls::class)->result()->getMethod('collectedNamedResolved')->returnType();

    expect($type)->toEqual(Type::arrayShape(Type::union(Type::int(), Type::string()), Type::array(['count' => Type::int(42)])));
});

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

it('preserves return branches without a resource alongside resource payloads', function () {
    $type = app(Analyzer::class)->analyzeClass(ResourceMethodCalls::class)->result()->getMethod('conditionalGroupsCall')->returnType();

    expect($type->keys())->toBe(['items', 'error']);
    expect($type->value['items'])->toBeInstanceOf(ResourceResponse::class)
        ->and($type->value['items']->isOptional())->toBeTrue();
    expect($type->value['error']->value)->toBe('unavailable')
        ->and($type->value['error']->isOptional())->toBeTrue();
});
