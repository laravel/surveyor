<?php

use Laravel\Surveyor\Analyzer\AnalyzedCache;
use Laravel\Surveyor\Analyzer\Analyzer;
use Laravel\Surveyor\Types\ArrayType;
use Laravel\Surveyor\Types\BoolType;
use Laravel\Surveyor\Types\FloatType;
use Laravel\Surveyor\Types\IntType;
use Laravel\Surveyor\Types\StringType;

uses()->group('integration');

beforeEach(function () {
    AnalyzedCache::clear();
});

afterEach(function () {
    AnalyzedCache::clear();
});

describe('basic array literals', function () {
    it('resolves simple list array', function () {
        $fixture = createPhpFixture('
namespace App;

class ListArrayTest
{
    public function test(): array
    {
        return ["a", "b", "c"];
    }
}');

        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyze($fixture)->result();

        $method = $result->getMethod('test');
        $returnType = $method->returnType();

        expect($returnType)->toBeInstanceOf(ArrayType::class);
        expect($returnType->isList())->toBeTrue();
        expect(count($returnType->value))->toBe(3);
        expect($returnType->value[0])->toBeInstanceOf(StringType::class);
        expect($returnType->value[0]->value)->toBe('a');
        expect($returnType->value[1])->toBeInstanceOf(StringType::class);
        expect($returnType->value[1]->value)->toBe('b');
        expect($returnType->value[2])->toBeInstanceOf(StringType::class);
        expect($returnType->value[2]->value)->toBe('c');

        unlink($fixture);
    });

    it('resolves simple keyed array', function () {
        $fixture = createPhpFixture('
namespace App;

class KeyedArrayTest
{
    public function test(): array
    {
        return ["name" => "Joe", "age" => 25];
    }
}');

        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyze($fixture)->result();

        $method = $result->getMethod('test');
        $returnType = $method->returnType();

        expect($returnType)->toBeInstanceOf(ArrayType::class);
        expect($returnType->isList())->toBeFalse();
        expect($returnType->value)->toHaveKey('name');
        expect($returnType->value)->toHaveKey('age');
        expect($returnType->value['name'])->toBeInstanceOf(StringType::class);
        expect($returnType->value['name']->value)->toBe('Joe');
        expect($returnType->value['age'])->toBeInstanceOf(IntType::class);
        expect($returnType->value['age']->value)->toBe(25);

        unlink($fixture);
    });

    it('resolves empty array', function () {
        $fixture = createPhpFixture('
namespace App;

class EmptyArrayTest
{
    public function test(): array
    {
        return [];
    }
}');

        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyze($fixture)->result();

        $method = $result->getMethod('test');
        $returnType = $method->returnType();

        expect($returnType)->toBeInstanceOf(ArrayType::class);
        expect($returnType->value)->toBe([]);

        unlink($fixture);
    });

    it('resolves mixed type list array', function () {
        $fixture = createPhpFixture('
namespace App;

class MixedListTest
{
    public function test(): array
    {
        return ["hello", 42, 3.14, true];
    }
}');

        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyze($fixture)->result();

        $method = $result->getMethod('test');
        $returnType = $method->returnType();

        expect($returnType)->toBeInstanceOf(ArrayType::class);
        expect($returnType->isList())->toBeTrue();
        expect(count($returnType->value))->toBe(4);
        expect($returnType->value[0])->toBeInstanceOf(StringType::class);
        expect($returnType->value[0]->value)->toBe('hello');
        expect($returnType->value[1])->toBeInstanceOf(IntType::class);
        expect($returnType->value[1]->value)->toBe(42);
        expect($returnType->value[2])->toBeInstanceOf(FloatType::class);
        expect($returnType->value[2]->value)->toBe(3.14);
        expect($returnType->value[3])->toBeInstanceOf(BoolType::class);
        expect($returnType->value[3]->value)->toBe(true);

        unlink($fixture);
    });

    it('resolves mixed type keyed array', function () {
        $fixture = createPhpFixture('
namespace App;

class MixedKeyedTest
{
    public function test(): array
    {
        return [
            "name" => "Joe",
            "age" => 30,
            "balance" => 100.50,
            "active" => true,
        ];
    }
}');

        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyze($fixture)->result();

        $method = $result->getMethod('test');
        $returnType = $method->returnType();

        expect($returnType)->toBeInstanceOf(ArrayType::class);
        expect($returnType->value)->toHaveKey('name');
        expect($returnType->value)->toHaveKey('age');
        expect($returnType->value)->toHaveKey('balance');
        expect($returnType->value)->toHaveKey('active');
        expect($returnType->value['name'])->toBeInstanceOf(StringType::class);
        expect($returnType->value['name']->value)->toBe('Joe');
        expect($returnType->value['age'])->toBeInstanceOf(IntType::class);
        expect($returnType->value['age']->value)->toBe(30);
        expect($returnType->value['balance'])->toBeInstanceOf(FloatType::class);
        expect($returnType->value['balance']->value)->toBe(100.50);
        expect($returnType->value['active'])->toBeInstanceOf(BoolType::class);
        expect($returnType->value['active']->value)->toBe(true);

        unlink($fixture);
    });
});

describe('nested arrays', function () {
    it('resolves nested keyed arrays', function () {
        $fixture = createPhpFixture('
namespace App;

class NestedKeyedTest
{
    public function test(): array
    {
        return [
            "user" => [
                "name" => "Joe",
                "email" => "joe@example.com",
            ],
        ];
    }
}');

        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyze($fixture)->result();

        $method = $result->getMethod('test');
        $returnType = $method->returnType();

        expect($returnType)->toBeInstanceOf(ArrayType::class);
        expect($returnType->value)->toHaveKey('user');
        expect($returnType->value['user'])->toBeInstanceOf(ArrayType::class);
        expect($returnType->value['user']->value)->toHaveKey('name');
        expect($returnType->value['user']->value)->toHaveKey('email');
        expect($returnType->value['user']->value['name']->value)->toBe('Joe');
        expect($returnType->value['user']->value['email']->value)->toBe('joe@example.com');

        unlink($fixture);
    });

    it('resolves nested list arrays', function () {
        $fixture = createPhpFixture('
namespace App;

class NestedListTest
{
    public function test(): array
    {
        return [
            ["a", "b"],
            ["c", "d"],
        ];
    }
}');

        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyze($fixture)->result();

        $method = $result->getMethod('test');
        $returnType = $method->returnType();

        expect($returnType)->toBeInstanceOf(ArrayType::class);
        expect($returnType->isList())->toBeTrue();
        expect(count($returnType->value))->toBe(2);
        expect($returnType->value[0])->toBeInstanceOf(ArrayType::class);
        expect($returnType->value[0]->value)->toHaveCount(2);
        expect($returnType->value[0]->value[0])->toBeInstanceOf(StringType::class);
        expect($returnType->value[0]->value[0]->value)->toBe('a');
        expect($returnType->value[0]->value[1])->toBeInstanceOf(StringType::class);
        expect($returnType->value[0]->value[1]->value)->toBe('b');
        expect($returnType->value[1])->toBeInstanceOf(ArrayType::class);
        expect($returnType->value[1]->value)->toHaveCount(2);
        expect($returnType->value[1]->value[0])->toBeInstanceOf(StringType::class);
        expect($returnType->value[1]->value[0]->value)->toBe('c');
        expect($returnType->value[1]->value[1])->toBeInstanceOf(StringType::class);
        expect($returnType->value[1]->value[1]->value)->toBe('d');
        expect($returnType->value[0]->isList())->toBeTrue();

        unlink($fixture);
    });
});

describe('arrays with variables', function () {
    it('resolves array with variable values', function () {
        $fixture = createPhpFixture('
namespace App;

class VariableArrayTest
{
    public function test(): array
    {
        $name = "Joe";
        $age = 25;

        return ["name" => $name, "age" => $age];
    }
}');

        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyze($fixture)->result();

        $method = $result->getMethod('test');
        $returnType = $method->returnType();

        expect($returnType)->toBeInstanceOf(ArrayType::class);
        expect($returnType->value)->toHaveKey('name');
        expect($returnType->value)->toHaveKey('age');
        expect($returnType->value['name'])->toBeInstanceOf(StringType::class);
        expect($returnType->value['name']->value)->toBe('Joe');
        expect($returnType->value['age'])->toBeInstanceOf(IntType::class);
        expect($returnType->value['age']->value)->toBe(25);

        unlink($fixture);
    });

    it('resolves array assigned to variable', function () {
        $fixture = createPhpFixture('
namespace App;

class AssignedArrayTest
{
    public function test(): array
    {
        $data = ["name" => "Joe", "age" => 25];

        return $data;
    }
}');

        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyze($fixture)->result();

        $method = $result->getMethod('test');
        $returnType = $method->returnType();

        expect($returnType)->toBeInstanceOf(ArrayType::class);
        expect($returnType->value)->toHaveKey('name');
        expect($returnType->value)->toHaveKey('age');
        expect($returnType->value['name']->value)->toBe('Joe');
        expect($returnType->value['age']->value)->toBe(25);

        unlink($fixture);
    });
});

describe('array unpacking', function () {
    it('handles unpacking in keyed array', function () {
        $fixture = createPhpFixture('
namespace App;

class SpreadTest
{
    public function test(): array
    {
        $ar1 = ["first" => "a", "second" => "b"];
        $result = [...$ar1, "name" => "Joe", "age" => 25];

        return $result;
    }
}');

        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyze($fixture)->result();

        $method = $result->getMethod('test');
        $returnType = $method->returnType();

        expect($returnType)->toBeInstanceOf(ArrayType::class);
        expect($returnType->value)->toHaveKey('first');
        expect($returnType->value)->toHaveKey('second');
        expect($returnType->value)->toHaveKey('name');
        expect($returnType->value)->toHaveKey('age');
        expect($returnType->value['first'])->toBeInstanceOf(StringType::class);
        expect($returnType->value['first']->value)->toBe('a');
        expect($returnType->value['second'])->toBeInstanceOf(StringType::class);
        expect($returnType->value['second']->value)->toBe('b');
        expect($returnType->value['name'])->toBeInstanceOf(StringType::class);
        expect($returnType->value['name']->value)->toBe('Joe');
        expect($returnType->value['age'])->toBeInstanceOf(IntType::class);
        expect($returnType->value['age']->value)->toBe(25);

        unlink($fixture);
    });

    it('handles unpacking in list array', function () {
        $fixture = createPhpFixture('
namespace App;

class SpreadListTest
{
    public function test(): array
    {
        $ar1 = ["a", "b"];
        $result = [...$ar1, "c", "d"];

        return $result;
    }
}');

        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyze($fixture)->result();

        $method = $result->getMethod('test');
        $returnType = $method->returnType();

        expect($returnType)->toBeInstanceOf(ArrayType::class);
        expect($returnType->isList())->toBeTrue();
        expect(count($returnType->value))->toBe(4);
        expect($returnType->value[0])->toBeInstanceOf(StringType::class);
        expect($returnType->value[0]->value)->toBe('a');
        expect($returnType->value[1])->toBeInstanceOf(StringType::class);
        expect($returnType->value[1]->value)->toBe('b');
        expect($returnType->value[2])->toBeInstanceOf(StringType::class);
        expect($returnType->value[2]->value)->toBe('c');
        expect($returnType->value[3])->toBeInstanceOf(StringType::class);
        expect($returnType->value[3]->value)->toBe('d');

        unlink($fixture);
    });

    it('handles multiple unpacking in list array', function () {
        $fixture = createPhpFixture('
namespace App;

class MultiSpreadTest
{
    public function test(): array
    {
        $ar1 = ["a"];
        $ar2 = ["b"];
        $result = [...$ar1, ...$ar2, "c"];

        return $result;
    }
}');

        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyze($fixture)->result();

        $method = $result->getMethod('test');
        $returnType = $method->returnType();

        expect($returnType)->toBeInstanceOf(ArrayType::class);
        expect($returnType->isList())->toBeTrue();
        expect(count($returnType->value))->toBe(3);
        expect($returnType->value[0])->toBeInstanceOf(StringType::class);
        expect($returnType->value[0]->value)->toBe('a');
        expect($returnType->value[1])->toBeInstanceOf(StringType::class);
        expect($returnType->value[1]->value)->toBe('b');
        expect($returnType->value[2])->toBeInstanceOf(StringType::class);
        expect($returnType->value[2]->value)->toBe('c');

        unlink($fixture);
    });

    it('handles unpacking with only unpacked arrays in keyed array', function () {
        $fixture = createPhpFixture('
namespace App;

class OnlySpreadTest
{
    public function test(): array
    {
        $ar1 = ["name" => "Joe"];
        $ar2 = ["age" => 25];
        $result = [...$ar1, ...$ar2];

        return $result;
    }
}');

        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyze($fixture)->result();

        $method = $result->getMethod('test');
        $returnType = $method->returnType();

        expect($returnType)->toBeInstanceOf(ArrayType::class);
        expect($returnType->value)->toHaveKey('name');
        expect($returnType->value)->toHaveKey('age');
        expect($returnType->value['name']->value)->toBe('Joe');
        expect($returnType->value['age']->value)->toBe(25);

        unlink($fixture);
    });
});
