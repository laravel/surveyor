<?php

use Laravel\Surveyor\Analyzed\ClassLikeResult;
use Laravel\Surveyor\Analyzed\MethodResult;
use Laravel\Surveyor\Analyzer\AnalyzedCache;
use Laravel\Surveyor\Analyzer\Analyzer;
use Laravel\Surveyor\Types\ArrayShapeType;
use Laravel\Surveyor\Types\ArrayType;
use Laravel\Surveyor\Types\BoolType;
use Laravel\Surveyor\Types\ClassType;
use Laravel\Surveyor\Types\IntType;
use Laravel\Surveyor\Types\StringType;
use Laravel\Surveyor\Types\Type;
use Laravel\Surveyor\Types\UnionType;
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
        expect($result->result())->toBeInstanceOf(ClassLikeResult::class);
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

    it('infers property type from default value when no type declaration', function () {
        $fixture = createPhpFixture('
namespace App;

class DefaultValueClass
{
    protected $with = [\'mainPost\'];
    protected $items = [];
    protected $counter = 5;
    protected $enabled = true;
}');

        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyze($fixture)->result();

        expect($result->hasProperty('with'))->toBeTrue();
        $withProp = $result->getProperty('with');
        expect($withProp->type)->toBeInstanceOf(ArrayType::class);

        expect($result->hasProperty('items'))->toBeTrue();
        $itemsProp = $result->getProperty('items');
        expect($itemsProp->type)->toBeInstanceOf(ArrayType::class);

        expect($result->hasProperty('counter'))->toBeTrue();
        $counterProp = $result->getProperty('counter');
        expect($counterProp->type)->toBeInstanceOf(IntType::class);

        expect($result->hasProperty('enabled'))->toBeTrue();
        $enabledProp = $result->getProperty('enabled');
        expect($enabledProp->type)->toBeInstanceOf(BoolType::class);

        unlink($fixture);
    });

    it('exposes class-level @property docblock tags as properties', function () {
        $fixture = createPhpFixture('
namespace App;

/**
 * @property int $id
 * @property string $email
 * @property string|null $remember_token
 * @property-read \Illuminate\Support\Carbon $created_at
 */
class DocBlockClass
{
}');

        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyze($fixture)->result();

        expect($result->hasProperty('id'))->toBeTrue();
        expect($result->getProperty('id')->fromDocBlock)->toBeTrue();
        expect($result->getProperty('id')->type)->toBeInstanceOf(IntType::class);

        expect($result->hasProperty('email'))->toBeTrue();
        expect($result->getProperty('email')->type)->toBeInstanceOf(StringType::class);

        expect($result->hasProperty('remember_token'))->toBeTrue();
        expect($result->getProperty('remember_token')->type->isNullable())->toBeTrue();

        expect($result->hasProperty('created_at'))->toBeTrue();

        unlink($fixture);
    });

    it('does not let class-level @property docblock tags overwrite real property declarations', function () {
        $fixture = createPhpFixture('
namespace App;

/**
 * @property string $name
 */
class MixedClass
{
    public int $name = 0;
}');

        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyze($fixture)->result();

        $nameProp = $result->getProperty('name');
        expect($nameProp->type)->toBeInstanceOf(IntType::class);
        expect($nameProp->fromDocBlock)->toBeFalse();

        unlink($fixture);
    });

    it('flags @property-read as readOnly and @property-write as writeOnly', function () {
        $fixture = createPhpFixture('
namespace App;

/**
 * @property string $name
 * @property-read int $id
 * @property-write string $password
 * @property string $email
 * @property-read string $email
 */
class FlagsClass
{
}');

        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyze($fixture)->result();

        expect($result->getProperty('name')->readOnly)->toBeFalse();
        expect($result->getProperty('name')->writeOnly)->toBeFalse();

        expect($result->getProperty('id')->readOnly)->toBeTrue();
        expect($result->getProperty('id')->writeOnly)->toBeFalse();

        expect($result->getProperty('password')->readOnly)->toBeFalse();
        expect($result->getProperty('password')->writeOnly)->toBeTrue();

        expect($result->getProperty('email')->readOnly)->toBeFalse();
        expect($result->getProperty('email')->writeOnly)->toBeFalse();

        unlink($fixture);
    });

    it('uses explicit type declaration over default value inference', function () {
        $fixture = createPhpFixture('
namespace App;

class ExplicitTypeClass
{
    protected int $counter = 5;

    /** @var list<string> */
    protected $tags = [];
}');

        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyze($fixture)->result();

        $counterProp = $result->getProperty('counter');
        expect($counterProp->type)->toBeInstanceOf(IntType::class);

        $tagsProp = $result->getProperty('tags');
        expect($tagsProp->type)->toBeInstanceOf(ArrayShapeType::class);

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

describe('analyzing interfaces', function () {
    it('returns an interface-typed ClassLikeResult with methods', function () {
        $fixture = createPhpFixture('
namespace App\\Contracts;

interface Sluggable
{
    public function toSlug(): string;

    public function isUnique(): bool;
}');

        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyze($fixture)->result();

        expect($result)->toBeInstanceOf(ClassLikeResult::class);
        expect($result->isInterface())->toBeTrue();
        expect($result->isClass())->toBeFalse();
        expect($result->name())->toBe('App\\Contracts\\Sluggable');

        expect($result->hasMethod('toSlug'))->toBeTrue();
        expect($result->hasMethod('isUnique'))->toBeTrue();

        unlink($fixture);
    });

    it('tracks transitive interface extends via reflection', function () {
        $fixture = createPhpFixture('
namespace App\\Contracts;

interface PrintableSluggable extends \\Stringable
{
    public function toSlug(): string;
}');

        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyze($fixture)->result();

        expect($result->extends())->toContain('Stringable');

        unlink($fixture);
    });

    it('captures constants declared in the interface body', function () {
        $fixture = createPhpFixture('
namespace App\\Contracts;

interface Sluggable
{
    public const DEFAULT_SEPARATOR = \'-\';
    public const MAX_LENGTH = 64;
}');

        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyze($fixture)->result();

        expect($result->isInterface())->toBeTrue();
        expect($result->hasConstant('DEFAULT_SEPARATOR'))->toBeTrue();
        expect($result->getConstant('DEFAULT_SEPARATOR')->type->id())->toBe('-');
        expect($result->hasConstant('MAX_LENGTH'))->toBeTrue();
        expect($result->getConstant('MAX_LENGTH')->type->id())->toBe('64');

        unlink($fixture);
    });

    it('captures @method docblock tags on interfaces', function () {
        $fixture = createPhpFixture('
namespace App\\Contracts;

/**
 * @method string make(string $key)
 */
interface Builder
{
}');

        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyze($fixture)->result();

        expect($result->isInterface())->toBeTrue();
        expect($result->hasMethod('make'))->toBeTrue();

        unlink($fixture);
    });

    it('does not crash when analyzing anonymous classes', function () {
        $fixture = createPhpFixture('
namespace App;

class Holder
{
    public function make()
    {
        return new class {
            public function inner(): string
            {
                return "x";
            }
        };
    }
}');

        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyze($fixture)->result();

        expect($result)->toBeInstanceOf(ClassLikeResult::class);
        expect($result->isClass())->toBeTrue();
        expect($result->name())->toBe('App\\Holder');
        expect($result->hasMethod('make'))->toBeTrue();
        expect($result->hasMethod('inner'))->toBeFalse();

        unlink($fixture);
    });

    it('does not crash when an anonymous class declares properties', function () {
        $fixture = createPhpFixture('
namespace App;

class Holder
{
    public function make()
    {
        return new class {
            public int $count = 0;
            public string $label = "x";
        };
    }
}');

        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyze($fixture)->result();

        expect($result)->toBeInstanceOf(ClassLikeResult::class);
        expect($result->name())->toBe('App\\Holder');
        expect($result->hasProperty('count'))->toBeFalse();
        expect($result->hasProperty('label'))->toBeFalse();

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
            || (Type::is($returnType, UnionType::class)))->toBeTrue();

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
