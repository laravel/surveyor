<?php

use Laravel\Surveyor\Types\ArrayType;
use Laravel\Surveyor\Types\IntType;
use Laravel\Surveyor\Types\StringType;
use Laravel\Surveyor\Types\Type;
use Laravel\Surveyor\Types\UnionType;

uses()->group('types');

describe('UnionType', function () {
    it('stores multiple types', function () {
        $union = new UnionType([Type::string(), Type::int()]);

        expect($union->types)->toHaveCount(2);
        expect($union->types[0])->toBeInstanceOf(StringType::class);
        expect($union->types[1])->toBeInstanceOf(IntType::class);
    });

    it('provides unique id based on types', function () {
        $union1 = new UnionType([Type::string('hello'), Type::int(1)]);
        $union2 = new UnionType([Type::string('hello'), Type::int(1)]);
        $union3 = new UnionType([Type::string('hello'), Type::int(2)]);

        expect($union1->id())->toBe($union2->id());
        expect($union1->id())->not->toBe($union3->id());
    });

    it('collapses to simplified union', function () {
        $union = new UnionType([Type::string(), Type::int()]);

        $collapsed = $union->collapse();

        expect($collapsed)->toBeInstanceOf(UnionType::class);
    });

    it('collapses multiple array types to single array', function () {
        $array1 = Type::array(['name' => Type::string('Joe')]);
        $array2 = Type::array(['name' => Type::string('Jane'), 'age' => Type::int(30)]);

        $union = new UnionType([$array1, $array2]);
        $collapsed = $union->collapse();

        expect($collapsed)->toBeInstanceOf(ArrayType::class);
        expect(array_keys($collapsed->value))->toContain('name');
        expect(array_keys($collapsed->value))->toContain('age');
    });

    it('marks shared keys as required when collapsing arrays', function () {
        $array1 = Type::array(['name' => Type::string('Joe'), 'id' => Type::int(1)]);
        $array2 = Type::array(['name' => Type::string('Jane'), 'id' => Type::int(2)]);

        $union = new UnionType([$array1, $array2]);
        $collapsed = $union->collapse();

        expect($collapsed)->toBeInstanceOf(ArrayType::class);
        expect($collapsed->value['name']->isOptional())->toBeFalse();
        expect($collapsed->value['id']->isOptional())->toBeFalse();
    });

    it('marks non-shared keys as optional when collapsing arrays', function () {
        $array1 = Type::array(['name' => Type::string('Joe')]);
        $array2 = Type::array(['name' => Type::string('Jane'), 'age' => Type::int(30)]);

        $union = new UnionType([$array1, $array2]);
        $collapsed = $union->collapse();

        expect($collapsed)->toBeInstanceOf(ArrayType::class);
        expect($collapsed->value['name']->isOptional())->toBeFalse();
        expect($collapsed->value['age']->isOptional())->toBeTrue();
    });
});
