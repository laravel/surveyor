<?php

use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Laravel\Surveyor\Analyzer\AnalyzedCache;
use Laravel\Surveyor\Analyzer\Analyzer;
use Laravel\Surveyor\Analyzer\ArrayableResolver;
use Laravel\Surveyor\Types\ArrayShapeType;
use Laravel\Surveyor\Types\ArrayType;
use Laravel\Surveyor\Types\ClassType;
use Laravel\Surveyor\Types\IntType;
use Laravel\Surveyor\Types\StringType;

uses()->group('integration');

beforeAll(function () {
    AnalyzedCache::clear();
});

afterAll(function () {
    AnalyzedCache::clear();
});

describe('ArrayableResolver substituteTemplateBindings', function () {
    it('substitutes @template placeholders in toArray() using caller ClassType generics', function () {
        $type = (new ClassType(LengthAwarePaginator::class))
            ->setGenericTypes([new IntType, new ClassType(User::class)]);

        $result = app(ArrayableResolver::class)->resolve($type);

        expect($result)->toBeInstanceOf(ArrayType::class);

        $data = $result->value['data'] ?? null;
        expect($data)->not->toBeNull();
        expect($data)->toBeInstanceOf(ArrayShapeType::class);

        // TValue (index 1, ClassType(User)): not the raw placeholder StringType('TValue')
        expect($data->valueType)->toBeInstanceOf(ClassType::class);
        expect($data->valueType->value)->toBe(User::class);

        // TKey (index 0, IntType): not the raw placeholder StringType('TKey')
        expect($data->keyType)->toBeInstanceOf(IntType::class);
    });

    it('returns the resolved ArrayType unchanged when the caller provides no generics', function () {
        $type = new ClassType(LengthAwarePaginator::class);

        $result = app(ArrayableResolver::class)->resolve($type);

        expect($result)->toBeInstanceOf(ArrayType::class);

        $data = $result->value['data'] ?? null;
        expect($data)->not->toBeNull();
        expect($data)->toBeInstanceOf(ArrayShapeType::class);
        expect($data->valueType)->toBeInstanceOf(StringType::class);
    });
});

describe('Reflector caller-side template binding', function () {
    it('binds TModel to the concrete Model subclass when resolving Builder methods via Reflector', function () {
        $fixture = createPhpFixture('
namespace App\\Test;

use App\\Models\\User;

class UserPaginatorController
{
    public function index()
    {
        return User::paginate(15);
    }
}
');

        $result = app(Analyzer::class)->analyze($fixture)->result();
        $returnType = $result->getMethod('index')->returnType();

        expect($returnType)->toBeInstanceOf(ClassType::class);
        expect($returnType->value)->toBe(LengthAwarePaginator::class);

        $generics = $returnType->genericTypes();
        expect($generics)->toHaveCount(2);

        expect($generics[0])->toBeInstanceOf(IntType::class);

        expect($generics[1])->toBeInstanceOf(ClassType::class);
        expect($generics[1]->value)->toBe(User::class);

        unlink($fixture);
    });

    it('produces a fully resolved data shape', function () {
        $fixture = createPhpFixture('
namespace App\\Test;

use App\\Models\\User;

class UserPaginatorController
{
    public function index()
    {
        return User::paginate(15);
    }
}
');

        $result = app(Analyzer::class)->analyze($fixture)->result();
        $returnType = $result->getMethod('index')->returnType();
        $resolved = app(ArrayableResolver::class)->resolve($returnType);

        expect($resolved)->toBeInstanceOf(ArrayType::class);

        $data = $resolved->value['data'] ?? null;
        expect($data)->not->toBeNull();
        expect($data)->toBeInstanceOf(ArrayShapeType::class);
        expect($data->valueType)->toBeInstanceOf(ClassType::class);
        expect($data->valueType->value)->toBe(User::class);

        unlink($fixture);
    });
});
