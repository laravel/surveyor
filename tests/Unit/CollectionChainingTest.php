<?php

use Laravel\Surveyor\Analyzer\AnalyzedCache;
use Laravel\Surveyor\Analyzer\Analyzer;
use Laravel\Surveyor\Types\ClassType;
use Laravel\Surveyor\Types\IntType;
use Laravel\Surveyor\Types\StringType;

uses()->group('integration');

beforeEach(function () {
    AnalyzedCache::clear();
});

afterEach(function () {
    AnalyzedCache::clear();
});

describe('Support\\Collection chaining', function () {
    it('infers generic types from collect() with a list', function () {
        $fixture = createPhpFixture('
namespace App;

class TestClass
{
    public function test()
    {
        return collect([\'a\', \'b\', \'c\']);
    }
}');

        $result = app(Analyzer::class)->analyze($fixture)->result();
        $returnType = $result->getMethod('test')->returnType();

        expect($returnType)->toBeInstanceOf(ClassType::class);
        expect($returnType->value)->toBe('Illuminate\Support\Collection');
        expect($returnType->genericTypes())->toHaveCount(2);
        expect($returnType->genericTypes()[0])->toBeInstanceOf(IntType::class);
        expect($returnType->genericTypes()[1])->toBeInstanceOf(StringType::class);

        unlink($fixture);
    });

    it('preserves generic types through filter()', function () {
        $fixture = createPhpFixture('
namespace App;

class TestClass
{
    public function test()
    {
        return collect([\'a\', \'b\', \'c\'])->filter();
    }
}');

        $result = app(Analyzer::class)->analyze($fixture)->result();
        $returnType = $result->getMethod('test')->returnType();

        expect($returnType)->toBeInstanceOf(ClassType::class);
        expect($returnType->value)->toBe('Illuminate\Support\Collection');
        expect($returnType->genericTypes())->toHaveCount(2);
        expect($returnType->genericTypes()[0])->toBeInstanceOf(IntType::class);
        expect($returnType->genericTypes()[1])->toBeInstanceOf(StringType::class);

        unlink($fixture);
    });

    it('resolves first() to TValue|null', function () {
        $fixture = createPhpFixture('
namespace App;

class TestClass
{
    public function test()
    {
        return collect([\'a\', \'b\', \'c\'])->first();
    }
}');

        $result = app(Analyzer::class)->analyze($fixture)->result();
        $returnType = $result->getMethod('test')->returnType();

        expect($returnType)->toBeInstanceOf(StringType::class);
        expect($returnType->nullable)->toBeTrue();

        unlink($fixture);
    });

    it('resolves first() after filter() to TValue|null', function () {
        $fixture = createPhpFixture('
namespace App;

class TestClass
{
    public function test()
    {
        return collect([\'a\', \'b\', \'c\'])->filter()->first();
    }
}');

        $result = app(Analyzer::class)->analyze($fixture)->result();
        $returnType = $result->getMethod('test')->returnType();

        expect($returnType)->toBeInstanceOf(StringType::class);
        expect($returnType->nullable)->toBeTrue();

        unlink($fixture);
    });
});

describe('Eloquent\\Collection chaining', function () {
    it('preserves generic types through filter() on Eloquent collection', function () {
        $fixture = createPhpFixture('
namespace App;

use App\Models\User;

class TestClass
{
    public function test()
    {
        return User::all()->filter();
    }
}');

        $result = app(Analyzer::class)->analyze($fixture)->result();
        $returnType = $result->getMethod('test')->returnType();

        expect($returnType)->toBeInstanceOf(ClassType::class);
        expect($returnType->value)->toBe('Illuminate\Database\Eloquent\Collection');
        expect($returnType->genericTypes())->toHaveCount(2);
        expect($returnType->genericTypes()[1])->toBeInstanceOf(ClassType::class);
        expect($returnType->genericTypes()[1]->value)->toBe('App\Models\User');

        unlink($fixture);
    });

    it('resolves first() on Eloquent collection to Model|null', function () {
        $fixture = createPhpFixture('
namespace App;

use App\Models\User;

class TestClass
{
    public function test()
    {
        return User::all()->first();
    }
}');

        $result = app(Analyzer::class)->analyze($fixture)->result();
        $returnType = $result->getMethod('test')->returnType();

        expect($returnType)->toBeInstanceOf(ClassType::class);
        expect($returnType->value)->toBe('App\Models\User');
        expect($returnType->nullable)->toBeTrue();

        unlink($fixture);
    });

    it('resolves first() after filter() on Eloquent collection to Model|null', function () {
        $fixture = createPhpFixture('
namespace App;

use App\Models\User;

class TestClass
{
    public function test()
    {
        return User::all()->filter()->first();
    }
}');

        $result = app(Analyzer::class)->analyze($fixture)->result();
        $returnType = $result->getMethod('test')->returnType();

        expect($returnType)->toBeInstanceOf(ClassType::class);
        expect($returnType->value)->toBe('App\Models\User');
        expect($returnType->nullable)->toBeTrue();

        unlink($fixture);
    });

    it('resolves get() on Eloquent builder to EloquentCollection<int, Model>', function () {
        $fixture = createPhpFixture('
namespace App;

use App\Models\User;

class TestClass
{
    public function test()
    {
        return User::where(\'active\', true)->get();
    }
}');

        $result = app(Analyzer::class)->analyze($fixture)->result();
        $returnType = $result->getMethod('test')->returnType();

        expect($returnType)->toBeInstanceOf(ClassType::class);
        expect($returnType->value)->toBe('Illuminate\Database\Eloquent\Collection');
        expect($returnType->genericTypes())->toHaveCount(2);
        expect($returnType->genericTypes()[1])->toBeInstanceOf(ClassType::class);
        expect($returnType->genericTypes()[1]->value)->toBe('App\Models\User');

        unlink($fixture);
    });

    it('resolves first() after builder chain to Model|null', function () {
        $fixture = createPhpFixture('
namespace App;

use App\Models\User;

class TestClass
{
    public function test()
    {
        return User::where(\'active\', true)->get()->first();
    }
}');

        $result = app(Analyzer::class)->analyze($fixture)->result();
        $returnType = $result->getMethod('test')->returnType();

        expect($returnType)->toBeInstanceOf(ClassType::class);
        expect($returnType->value)->toBe('App\Models\User');
        expect($returnType->nullable)->toBeTrue();

        unlink($fixture);
    });
});
