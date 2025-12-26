<?php

use Laravel\Surveyor\Types\ArrayType;
use Laravel\Surveyor\Types\BoolType;
use Laravel\Surveyor\Types\CallableType;
use Laravel\Surveyor\Types\ClassType;
use Laravel\Surveyor\Types\FloatType;
use Laravel\Surveyor\Types\IntType;
use Laravel\Surveyor\Types\MixedType;
use Laravel\Surveyor\Types\NeverType;
use Laravel\Surveyor\Types\NullType;
use Laravel\Surveyor\Types\NumberType;
use Laravel\Surveyor\Types\ObjectType;
use Laravel\Surveyor\Types\StringType;
use Laravel\Surveyor\Types\Type;
use Laravel\Surveyor\Types\UnionType;
use Laravel\Surveyor\Types\VoidType;

uses()->group('types');

describe('factory methods', function () {
    it('creates string type', function () {
        $type = Type::string();
        expect($type)->toBeInstanceOf(StringType::class);
    });

    it('creates string type with value', function () {
        $type = Type::string('hello');
        expect($type)->toBeInstanceOf(StringType::class);
        expect($type->value)->toBe('hello');
    });

    it('creates int type', function () {
        $type = Type::int();
        expect($type)->toBeInstanceOf(IntType::class);
    });

    it('creates int type with value', function () {
        $type = Type::int(42);
        expect($type)->toBeInstanceOf(IntType::class);
        expect($type->value)->toBe(42);
    });

    it('creates float type', function () {
        $type = Type::float(12.4);
        expect($type)->toBeInstanceOf(FloatType::class);
        expect($type->value)->toBe(12.4);
    });

    it('creates bool type', function () {
        $type = Type::bool();
        expect($type)->toBeInstanceOf(BoolType::class);
    });

    it('creates bool type with value', function () {
        $typeTrue = Type::bool(true);
        $typeFalse = Type::bool(false);
        expect($typeTrue)->toBeInstanceOf(BoolType::class);
        expect($typeFalse)->toBeInstanceOf(BoolType::class);
        expect($typeTrue->value)->toBeTrue();
        expect($typeFalse->value)->toBeFalse();
    });

    it('creates null type', function () {
        $type = Type::null();
        expect($type)->toBeInstanceOf(NullType::class);
    });

    it('creates void type', function () {
        $type = Type::void();
        expect($type)->toBeInstanceOf(VoidType::class);
    });

    it('creates mixed type', function () {
        $type = Type::mixed();
        expect($type)->toBeInstanceOf(MixedType::class);
    });

    it('creates never type', function () {
        $type = Type::never();
        expect($type)->toBeInstanceOf(NeverType::class);
    });

    it('creates object type', function () {
        $type = Type::object();
        expect($type)->toBeInstanceOf(ObjectType::class);
    });

    it('creates number type', function () {
        $type = Type::number();
        expect($type)->toBeInstanceOf(NumberType::class);
    });

    it('creates array type', function () {
        $type = Type::array([]);
        expect($type)->toBeInstanceOf(ArrayType::class);
    });

    it('creates callable type', function () {
        $type = Type::callable([]);
        expect($type)->toBeInstanceOf(CallableType::class);
    });

    it('creates callable type with return type', function () {
        $returnType = Type::string();
        $type = Type::callable([], $returnType);
        expect($type)->toBeInstanceOf(CallableType::class);
    });
});

describe('Type::from() conversion', function () {
    it('returns type instance unchanged', function () {
        $original = Type::string();
        $result = Type::from($original);
        expect($result)->toBe($original);
    });

    it('converts null to NullType', function () {
        $result = Type::from(null);
        expect($result)->toBeInstanceOf(NullType::class);
    });

    it('converts int to IntType', function () {
        $result = Type::from(42);
        expect($result)->toBeInstanceOf(IntType::class);
        expect($result->value)->toBe(42);
    });

    it('converts float to FloatType', function () {
        $result = Type::from(3.14);
        expect($result)->toBeInstanceOf(FloatType::class);
    });

    it('converts bool to BoolType', function () {
        $result = Type::from(true);
        expect($result)->toBeInstanceOf(BoolType::class);
        expect($result->value)->toBeTrue();
    });

    it('converts array to ArrayType', function () {
        $result = Type::from(['a', 'b']);
        expect($result)->toBeInstanceOf(ArrayType::class);
    });

    it('converts string type names to types', function () {
        expect(Type::from('array'))->toBeInstanceOf(ArrayType::class);
        expect(Type::from('void'))->toBeInstanceOf(VoidType::class);
        expect(Type::from('mixed'))->toBeInstanceOf(MixedType::class);
        expect(Type::from('float'))->toBeInstanceOf(FloatType::class);
        expect(Type::from('int'))->toBeInstanceOf(IntType::class);
        expect(Type::from('string'))->toBeInstanceOf(StringType::class);
        expect(Type::from('bool'))->toBeInstanceOf(BoolType::class);
        expect(Type::from('null'))->toBeInstanceOf(NullType::class);
        expect(Type::from('callable'))->toBeInstanceOf(CallableType::class);
        expect(Type::from('true'))->toBeInstanceOf(BoolType::class);
        expect(Type::from('false'))->toBeInstanceOf(BoolType::class);
    });

    it('converts callable string to CallableType', function () {
        $result = Type::from('callable');
        expect($result)->toBeInstanceOf(CallableType::class);
    });
});

describe('Type::union()', function () {
    it('creates union of multiple types', function () {
        $union = Type::union(Type::string(), Type::int());
        expect($union)->toBeInstanceOf(UnionType::class);
        expect($union->types)->toHaveCount(2);
    });

    it('returns single type when only one provided', function () {
        $single = Type::union(Type::string());
        expect($single)->toBeInstanceOf(StringType::class);
    });

    it('returns mixed when no types provided', function () {
        $empty = Type::union();
        expect($empty)->toBeInstanceOf(MixedType::class);
    });

    it('flattens nested unions', function () {
        $nested = Type::union(
            Type::union(Type::string(), Type::int()),
            Type::bool()
        );
        expect($nested)->toBeInstanceOf(UnionType::class);
        expect($nested->types)->toHaveCount(3);
    });

    it('deduplicates identical types', function () {
        $union = Type::union(Type::string(), Type::string(), Type::int());
        expect($union)->toBeInstanceOf(UnionType::class);
        expect($union->types)->toHaveCount(2);
    });

    it('removes mixed types from union', function () {
        $union = Type::union(Type::string(), Type::mixed(), Type::int());
        expect($union)->toBeInstanceOf(UnionType::class);
        expect($union->types)->toHaveCount(2);
    });

    it('handles null type by making other types nullable', function () {
        $union = Type::union(Type::string(), Type::null());
        expect($union)->toBeInstanceOf(StringType::class);
        expect($union->isNullable())->toBeTrue();
    });
});

describe('Type::isSame()', function () {
    it('returns true for identical types', function () {
        $type1 = Type::string('hello');
        $type2 = Type::string('hello');
        expect(Type::isSame($type1, $type2))->toBeTrue();
    });

    it('returns false for different types', function () {
        $type1 = Type::string();
        $type2 = Type::int();
        expect(Type::isSame($type1, $type2))->toBeFalse();
    });

    it('returns false for same type with different values', function () {
        $type1 = Type::string('hello');
        $type2 = Type::string('world');
        expect(Type::isSame($type1, $type2))->toBeFalse();
    });
});

describe('Type::is()', function () {
    it('returns true for matching type class', function () {
        $type = Type::string();
        expect(Type::is($type, StringType::class))->toBeTrue();
    });

    it('returns false for non-matching type class', function () {
        $type = Type::string();
        expect(Type::is($type, IntType::class))->toBeFalse();
    });

    it('returns true if any of multiple types match', function () {
        $type = Type::string();
        expect(Type::is($type, IntType::class, StringType::class))->toBeTrue();
    });
});

describe('type properties', function () {
    it('tracks nullable state', function () {
        $type = Type::string();
        expect($type->isNullable())->toBeFalse();

        $type->nullable(true);
        expect($type->isNullable())->toBeTrue();

        $type->nullable(false);
        expect($type->isNullable())->toBeFalse();
    });

    it('tracks required state', function () {
        $type = Type::string();
        expect($type->isOptional())->toBeFalse();

        $type->optional(true);
        expect($type->isOptional())->toBeTrue();

        $type->required(true);
        expect($type->isOptional())->toBeFalse();
    });

    it('returns unique id', function () {
        $type1 = Type::string('hello');
        $type2 = Type::string('world');

        expect($type1->id())->not->toBe($type2->id());
    });

    it('returns string representation via toString', function () {
        $type = Type::string('hello');
        $str = $type->toString();

        expect($str)->toContain(StringType::class);
        expect($str)->toContain('hello');
    });
});

describe('ClassType', function () {
    it('creates class type from string', function () {
        $type = Type::string(DateTime::class);
        expect($type)->toBeInstanceOf(ClassType::class);
        expect($type->value)->toBe('DateTime');
    });

    it('strips leading backslash from class name', function () {
        $type = new ClassType('\DateTime');
        expect($type->value)->toBe('DateTime');
    });

    it('resolves class name', function () {
        $type = new ClassType(DateTime::class);
        expect($type->resolved())->toBe(DateTime::class);
    });

    it('allows setting generic types', function () {
        $type = new ClassType('Collection');
        $type->setGenericTypes([Type::string()]);

        expect($type)->toBeInstanceOf(ClassType::class);
    });

    it('allows setting constructor arguments', function () {
        $type = new ClassType('DateTime');
        $type->setConstructorArguments(['2024-01-01']);

        expect($type)->toBeInstanceOf(ClassType::class);
    });

    it('returns generic types via getter', function () {
        $type = new ClassType('Collection');
        $genericType = new ClassType('User');
        $type->setGenericTypes(['T' => $genericType]);

        $generics = $type->genericTypes();
        expect($generics)->toHaveKey('T');
        expect($generics['T'])->toBe($genericType);
    });

    it('includes generics in id', function () {
        $typeWithoutGenerics = new ClassType('HasMany');
        $typeWithGenerics = new ClassType('HasMany');
        $typeWithGenerics->setGenericTypes([
            'TRelatedModel' => new ClassType('Post'),
        ]);

        expect($typeWithoutGenerics->id())->toBe('HasMany');
        expect($typeWithGenerics->id())->toBe('HasMany<Post>');
    });

    it('is more specific when it has generics and other does not', function () {
        $typeWithoutGenerics = new ClassType('HasMany');
        $typeWithGenerics = new ClassType('HasMany');
        $typeWithGenerics->setGenericTypes([
            'TRelatedModel' => new ClassType('Post'),
        ]);

        expect($typeWithGenerics->isMoreSpecificThan($typeWithoutGenerics))->toBeTrue();
        expect($typeWithoutGenerics->isMoreSpecificThan($typeWithGenerics))->toBeFalse();
    });

    it('is not more specific than different class', function () {
        $hasMany = new ClassType('HasMany');
        $hasMany->setGenericTypes(['T' => new ClassType('Post')]);

        $belongsTo = new ClassType('BelongsTo');

        expect($hasMany->isMoreSpecificThan($belongsTo))->toBeFalse();
    });
});

describe('Type::union() with ClassType generics', function () {
    it('keeps the more specific type with generics', function () {
        $typeWithoutGenerics = new ClassType('HasMany');
        $typeWithGenerics = new ClassType('HasMany');
        $typeWithGenerics->setGenericTypes([
            'TRelatedModel' => new ClassType('Post'),
        ]);

        $union = Type::union($typeWithoutGenerics, $typeWithGenerics);

        expect($union)->toBeInstanceOf(ClassType::class);
        expect($union->genericTypes())->toHaveCount(1);
        expect($union->genericTypes()['TRelatedModel']->value)->toBe('Post');
    });

    it('keeps the more specific type regardless of order', function () {
        $typeWithoutGenerics = new ClassType('HasMany');
        $typeWithGenerics = new ClassType('HasMany');
        $typeWithGenerics->setGenericTypes([
            'TRelatedModel' => new ClassType('Post'),
        ]);

        $union = Type::union($typeWithGenerics, $typeWithoutGenerics);

        expect($union)->toBeInstanceOf(ClassType::class);
        expect($union->genericTypes())->toHaveCount(1);
    });
});
