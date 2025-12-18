<?php

use Laravel\Surveyor\Analyzed\ClassResult;
use Laravel\Surveyor\Analyzed\MethodResult;
use Laravel\Surveyor\Analyzer\AnalyzedCache;
use Laravel\Surveyor\Analyzer\Analyzer;
use Laravel\Surveyor\Types\ClassType;
use Laravel\Surveyor\Types\StringType;
use Laravel\Surveyor\Types\Type;
use Laravel\Surveyor\Types\VoidType;

uses()->group('integration');

beforeEach(function () {
    AnalyzedCache::clear();
});

afterEach(function () {
    AnalyzedCache::clear();
});

describe('analyzing classes', function () {
    it('analyzes a simple class', function () {
        $fixture = createPhpFixture('
namespace App\\Test;

class SimpleClass
{
    public function hello(): string
    {
        return "hello";
    }
}');

        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyze($fixture);

        expect($result->analyzed())->not->toBeNull();
        expect($result->result())->toBeInstanceOf(ClassResult::class);
        expect($result->result()->name())->toBe('App\\Test\\SimpleClass');
        expect($result->result()->namespace())->toBe('App\\Test');

        unlink($fixture);
    });

    it('extracts methods from class', function () {
        $fixture = createPhpFixture('
namespace App;

class MethodClass
{
    public function methodOne(): void
    {
    }

    public function methodTwo(): string
    {
        return "test";
    }
}');

        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyze($fixture)->result();

        expect($result->hasMethod('methodOne'))->toBeTrue();
        expect($result->hasMethod('methodTwo'))->toBeTrue();
        expect($result->hasMethod('nonExistent'))->toBeFalse();

        $methodOne = $result->getMethod('methodOne');
        expect($methodOne)->toBeInstanceOf(MethodResult::class);
        expect($methodOne->name())->toBe('methodOne');

        unlink($fixture);
    });

    it('extracts method return types', function () {
        $fixture = createPhpFixture('
namespace App;

class ReturnTypeClass
{
    public function returnsVoid(): void
    {
    }
}');

        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyze($fixture)->result();

        $voidMethod = $result->getMethod('returnsVoid');
        expect($voidMethod->returnType())->toBeInstanceOf(VoidType::class);

        unlink($fixture);
    });

    it('extracts method parameters via scope', function () {
        $fixture = createPhpFixture('
namespace App;

class ParameterClass
{
    public function withParams(string $name, int $age): void
    {
    }
}');

        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyze($fixture)->result();

        $method = $result->getMethod('withParams');
        expect($method)->not->toBeNull();
        expect($method->name())->toBe('withParams');

        unlink($fixture);
    });

    it('extracts class properties', function () {
        $fixture = createPhpFixture('
namespace App;

class PropertyClass
{
    public string $name;
    protected int $age;
    private bool $active;
}');

        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyze($fixture)->result();

        expect($result->hasProperty('name'))->toBeTrue();
        expect($result->hasProperty('age'))->toBeTrue();
        expect($result->hasProperty('active'))->toBeTrue();

        $nameProp = $result->getProperty('name');
        expect($nameProp->visibility)->toBe('public');
        expect($nameProp->type)->toBeInstanceOf(StringType::class);

        $ageProp = $result->getProperty('age');
        expect($ageProp->visibility)->toBe('protected');

        unlink($fixture);
    });

    it('extracts promoted constructor properties', function () {
        $fixture = createPhpFixture('
namespace App;

class PromotedClass
{
    public function __construct(
        public string $name,
        protected int $age,
    ) {
    }
}');

        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyze($fixture)->result();

        expect($result->hasProperty('name'))->toBeTrue();
        expect($result->hasProperty('age'))->toBeTrue();

        $nameProp = $result->getProperty('name');
        expect($nameProp->visibility)->toBe('public');
        expect($nameProp->type)->toBeInstanceOf(StringType::class);

        unlink($fixture);
    });
});

describe('analyzing extends and implements', function () {
    it('tracks class extends', function () {
        $fixture = createPhpFixture('
namespace App;

use Exception;

class CustomException extends Exception
{
}');

        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyze($fixture)->result();

        $extends = $result->extends();
        expect($extends)->toContain('Exception');

        unlink($fixture);
    });

    it('tracks class implements', function () {
        $fixture = createPhpFixture('
namespace App;

use JsonSerializable;

class JsonClass implements JsonSerializable
{
    public function jsonSerialize(): array
    {
        return [];
    }
}');

        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyze($fixture)->result();

        expect($result->implements('JsonSerializable'))->toBeTrue();

        unlink($fixture);
    });

    it('tracks multiple interfaces', function () {
        $fixture = createPhpFixture('
namespace App;

use JsonSerializable;
use Stringable;

class MultiInterface implements JsonSerializable, Stringable
{
    public function jsonSerialize(): array
    {
        return [];
    }

    public function __toString(): string
    {
        return "";
    }
}');

        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyze($fixture)->result();

        expect($result->implements('JsonSerializable'))->toBeTrue();
        expect($result->implements('Stringable'))->toBeTrue();

        unlink($fixture);
    });
});

describe('analyzing return values', function () {
    it('tracks return statements in methods', function () {
        $fixture = createPhpFixture('
namespace App;

class ReturnClass
{
    public function getValue(): string
    {
        return "hello";
    }
}');

        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyze($fixture)->result();

        $method = $result->getMethod('getValue');
        $returnType = $method->returnType();

        expect(Type::is($returnType, StringType::class)
            || (Type::is($returnType, \Laravel\Surveyor\Types\UnionType::class)))->toBeTrue();

        unlink($fixture);
    });

    it('handles class return types', function () {
        $fixture = createPhpFixture('
namespace App;

use DateTime;

class DateClass
{
    public function getDate(): DateTime
    {
        return new DateTime();
    }
}');

        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyze($fixture)->result();

        $method = $result->getMethod('getDate');
        $returnType = $method->returnType();

        expect($returnType)->toBeInstanceOf(ClassType::class);
        expect($returnType->value)->toBe('DateTime');

        unlink($fixture);
    });
});

describe('scope and namespace handling', function () {
    it('resolves use statements', function () {
        $fixture = createPhpFixture('
namespace App\\Controllers;

use App\\Models\\User;
use Illuminate\\Http\\Request as HttpRequest;

class UserController
{
    public function show(User $user): void
    {
    }
}');

        $analyzer = app(Analyzer::class);
        $scope = $analyzer->analyze($fixture)->analyzed();

        expect($scope->getUse('User'))->toBe('App\\Models\\User');
        expect($scope->getUse('HttpRequest'))->toBe('Illuminate\\Http\\Request');

        unlink($fixture);
    });

    it('tracks namespace correctly', function () {
        $fixture = createPhpFixture('
namespace App\\Http\\Controllers;

class TestController
{
}');

        $analyzer = app(Analyzer::class);
        $scope = $analyzer->analyze($fixture)->analyzed();

        expect($scope->namespace())->toBe('App\\Http\\Controllers');

        unlink($fixture);
    });
});
