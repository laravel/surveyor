<?php

use App\Data\AlternativeJsonData;
use App\Data\ArrayableList;
use App\Data\DualSerializedData;
use App\Data\FluentSettings;
use App\Data\InheritedJsonableData;
use App\Data\JsonableArrayData;
use App\Data\JsonableData;
use App\Data\JsonObjectData;
use App\Data\LiteralJsonData;
use App\Data\SerializableData;
use App\Data\UnknownJsonData;
use App\Models\User;
use Illuminate\Support\Collection;
use Laravel\Surveyor\Analyzer\AnalyzedCache;
use Laravel\Surveyor\Analyzer\ArrayableResolver;
use Laravel\Surveyor\Types\ArrayShapeType;
use Laravel\Surveyor\Types\ArrayType;
use Laravel\Surveyor\Types\ClassType;
use Laravel\Surveyor\Types\IntType;
use Laravel\Surveyor\Types\NullType;
use Laravel\Surveyor\Types\StringType;
use Laravel\Surveyor\Types\Type;
use Laravel\Surveyor\Types\UnionType;

beforeEach(fn () => AnalyzedCache::clear());
afterEach(fn () => AnalyzedCache::clear());

it('preserves literal and encoded JSON alternatives', function () {
    $type = app(ArrayableResolver::class)->resolveJsonResponse(new ClassType(AlternativeJsonData::class));

    expect($type)->toBeInstanceOf(UnionType::class)
        ->and($type->types)->toHaveCount(2)
        ->and($type->types[0]->value['count'])->toBeInstanceOf(IntType::class)
        ->and($type->types[1]->value['name'])->toBeInstanceOf(StringType::class);
});

it('resolves Jsonable data passed through json_encode without executing it', function () {
    $type = app(ArrayableResolver::class)->resolveJsonResponse(new ClassType(JsonableData::class));

    expect($type)->toBeInstanceOf(ArrayType::class)
        ->and($type->value['label'])->toBeInstanceOf(StringType::class)
        ->and($type->value['count'])->toBeInstanceOf(IntType::class)
        ->and($type->value['nested'])->toBeInstanceOf(ArrayType::class);
});

it('resolves a literal JSON string', function () {
    $type = app(ArrayableResolver::class)->resolveJsonResponse(new ClassType(LiteralJsonData::class));

    expect($type)->toBeInstanceOf(ArrayType::class)
        ->and($type->value['label'])->toBeInstanceOf(StringType::class)
        ->and($type->value['class'])->toBeInstanceOf(StringType::class)
        ->and($type->value['nested'])->toBeInstanceOf(ArrayType::class)
        ->and($type->value['count'])->toBeInstanceOf(NullType::class);
});

it('leaves arbitrary JSON strings unresolved', function () {
    expect(app(ArrayableResolver::class)->resolveJsonResponse(new ClassType(UnknownJsonData::class)))->toBeNull();
});

it('resolves Arrayable list shapes', function () {
    $type = app(ArrayableResolver::class)->resolve(new ClassType(ArrayableList::class));

    expect($type)->toBeInstanceOf(ArrayShapeType::class)
        ->and($type->valueType)->toBeInstanceOf(IntType::class);
});

it('resolves inherited Jsonable transformations', function () {
    $type = app(ArrayableResolver::class)->resolveJsonResponse(new ClassType(InheritedJsonableData::class));

    expect($type)->toBeInstanceOf(ArrayType::class)
        ->and($type->value['count'])->toBeInstanceOf(IntType::class);
});

it('resolves JsonSerializable data', function () {
    $type = app(ArrayableResolver::class)->resolve(new ClassType(SerializableData::class));

    expect($type)->toBeInstanceOf(ArrayType::class)
        ->and($type->value['label'])->toBeInstanceOf(StringType::class);
});

it('preserves JSON_FORCE_OBJECT for nested lists and named arguments', function () {
    $type = app(ArrayableResolver::class)->resolveJsonResponse(new ClassType(JsonObjectData::class));

    expect($type)->toBeInstanceOf(ArrayType::class)
        ->and($type->value['numbers'])->toBeInstanceOf(ArrayShapeType::class)
        ->and($type->value['numbers']->keyType)->toBeInstanceOf(StringType::class);
});

it('preserves model and generic collection types for their dedicated analyzers', function () {
    $resolver = app(ArrayableResolver::class);

    expect($resolver->resolve(new ClassType(User::class)))->toBeNull()
        ->and($resolver->resolve((new ClassType(Collection::class))->setGenericTypes([
            Type::int(), new ClassType(User::class),
        ])))->toBeNull();
});

it('ignores keyless shapes from inherited generic serializers', function () {
    $resolver = app(ArrayableResolver::class);

    expect($resolver->resolve(new ClassType(FluentSettings::class)))->toBeNull()
        ->and($resolver->resolveJsonResponse(new ClassType(FluentSettings::class)))->toBeNull();
});

it('follows Laravel serializer precedence for arrays and JSON responses', function (string $class) {
    $resolver = app(ArrayableResolver::class);

    expect($resolver->resolve(new ClassType($class))->keys())->toBe(['from_array'])
        ->and($resolver->resolveJsonResponse(new ClassType($class))->keys())->toBe(['from_json']);
})->with([
    'JsonSerializable' => [DualSerializedData::class],
    'Jsonable' => [JsonableArrayData::class],
]);

it('does not use toJson() when converting to an array', function () {
    expect(app(ArrayableResolver::class)->resolve(new ClassType(JsonableData::class)))->toBeNull();
});
