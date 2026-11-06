<?php

use App\Models\User;
use Laravel\Surveyor\Analyzer\AnalyzedCache;
use Laravel\Surveyor\Analyzer\Analyzer;
use Laravel\Surveyor\Types\BoolType;
use Laravel\Surveyor\Types\ClassType;
use Laravel\Surveyor\Types\StringType;
use Laravel\Surveyor\Types\Type;
use Laravel\Surveyor\Types\UnionType;

uses()->group('integration');

beforeEach(function () {
    AnalyzedCache::clear();
});

afterEach(function () {
    AnalyzedCache::clear();
});

function findStringType(Laravel\Surveyor\Types\Contracts\Type $type): ?StringType
{
    if ($type instanceof StringType) {
        return $type;
    }

    if ($type instanceof UnionType) {
        foreach ($type->types as $inner) {
            if ($inner instanceof StringType) {
                return $inner;
            }
        }
    }

    return null;
}

describe('Facade method resolution', function () {
    it('resolves Config::string() to a string return type via the underlying Repository', function () {
        $fixture = createPhpFixture('
namespace App\\Test;

use Illuminate\\Support\\Facades\\Config;

class ConfigController
{
    public function appName(): string
    {
        return Config::string(\'app.name\');
    }
}');

        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyze($fixture)->result();

        $returnType = $result->getMethod('appName')->returnType();

        expect(Type::is($returnType, StringType::class) || $returnType instanceof UnionType)->toBeTrue();
        expect(findStringType($returnType))->not->toBeNull();

        unlink($fixture);
    });
});

describe('Facade subclass methods', function () {
    it('resolves a method the facade declares itself from that declaration', function () {
        $fixture = createPhpFixture('
namespace App\\Test;

use App\\Facades\\CurrentUser;

class ProfileController
{
    public function owner()
    {
        return CurrentUser::user();
    }
}');

        $returnType = app(Analyzer::class)->analyze($fixture)->result()->getMethod('owner')->returnType();

        expect($returnType)->toBeInstanceOf(ClassType::class);
        expect($returnType->value)->toBe(User::class);

        unlink($fixture);
    });

    it('still hands a method the facade does not declare to the object behind it', function () {
        $fixture = createPhpFixture('
namespace App\\Test;

use App\\Facades\\CurrentUser;

class ProfileController
{
    public function guest()
    {
        return CurrentUser::guest();
    }
}');

        $returnType = app(Analyzer::class)->analyze($fixture)->result()->getMethod('guest')->returnType();

        expect($returnType)->toBeInstanceOf(BoolType::class);

        unlink($fixture);
    });

    it('hands a protected static method on the facade to the object behind it', function () {
        $fixture = createPhpFixture('
namespace App\\Test;

use App\\Facades\\CurrentUser;

class ProfileController
{
    public function check()
    {
        return CurrentUser::check();
    }
}');

        $returnType = app(Analyzer::class)->analyze($fixture)->result()->getMethod('check')->returnType();

        expect($returnType)->toBeInstanceOf(BoolType::class);

        unlink($fixture);
    });
});
