<?php

use Laravel\Surveyor\Types\ArrayShapeType;
use Laravel\Surveyor\Types\ArrayType;
use Laravel\Surveyor\Types\MixedType;
use Laravel\Surveyor\Types\Type;
use Laravel\Surveyor\Types\UnionType;

uses()->group('types');

describe('ArrayType', function () {
    it('stores array value', function () {
        $array = Type::array(['key' => Type::string()]);

        expect($array)->toBeInstanceOf(ArrayType::class);
        expect($array->value)->toHaveKey('key');
    });

    it('returns array keys', function () {
        $array = Type::array([
            'name' => Type::string(),
            'age' => Type::int(),
        ]);

        expect($array->keys())->toBe(['name', 'age']);
    });

    it('returns key type as union of all key types', function () {
        $array = Type::array([
            'name' => Type::string(),
            0 => Type::int(),
        ]);

        $keyType = $array->keyType();
        expect($keyType)->toBeInstanceOf(UnionType::class);
    });

    it('returns union of string and int for empty array key type', function () {
        $array = Type::array([]);

        $keyType = $array->keyType();
        expect($keyType)->toBeInstanceOf(UnionType::class);
    });

    it('returns value type as union of all value types', function () {
        $array = Type::array([
            'name' => Type::string('Joe'),
            'age' => Type::int(30),
        ]);

        $valueType = $array->valueType();
        expect($valueType)->toBeInstanceOf(UnionType::class);
        expect($valueType->types)->toHaveCount(2);
    });

    it('returns mixed for empty array value type', function () {
        $array = Type::array([]);

        $valueType = $array->valueType();
        expect($valueType)->toBeInstanceOf(MixedType::class);
    });

    it('detects list arrays', function () {
        $list = Type::array([0 => Type::string(), 1 => Type::string()]);
        $assoc = Type::array(['a' => Type::string(), 'b' => Type::string()]);

        expect($list->isList())->toBeTrue();
        expect($assoc->isList())->toBeFalse();
    });

    it('provides unique id based on value', function () {
        $array1 = Type::array(['name' => Type::string()]);
        $array2 = Type::array(['name' => Type::string()]);
        $array3 = Type::array(['age' => Type::int()]);

        expect($array1->id())->toBe($array2->id());
        expect($array1->id())->not->toBe($array3->id());
    });

    it('is more specific than ArrayShapeType when not empty', function () {
        $array = Type::array(['name' => Type::string()]);
        $shape = Type::arrayShape(Type::string(), Type::mixed());

        expect($array->isMoreSpecificThan($shape))->toBeTrue();
    });

    it('is not more specific when empty', function () {
        $array = Type::array([]);
        $shape = Type::arrayShape(Type::string(), Type::mixed());

        expect($array->isMoreSpecificThan($shape))->toBeFalse();
    });
});

describe('ArrayShapeType', function () {
    it('creates array shape with key and item types', function () {
        $shape = Type::arrayShape(Type::string(), Type::int());

        expect($shape)->toBeInstanceOf(ArrayShapeType::class);
    });
});
