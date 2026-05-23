<?php

use Laravel\Surveyor\Analyzed\ClassLikeResult;
use Laravel\Surveyor\Analyzer\AnalyzedCache;
use Laravel\Surveyor\Analyzer\Analyzer;
use Laravel\Surveyor\Types\ClassType;
use Laravel\Surveyor\Types\Contracts\Type as TypeContract;
use Laravel\Surveyor\Types\Entities\InertiaRender;
use Laravel\Surveyor\Types\IntType;
use Laravel\Surveyor\Types\StringType;
use Laravel\Surveyor\Types\UnionType;

uses()->group('integration');

beforeEach(function () {
    AnalyzedCache::clear();
});

afterEach(function () {
    AnalyzedCache::clear();
});

function findInertiaRender(TypeContract $type): ?InertiaRender
{
    if ($type instanceof InertiaRender) {
        return $type;
    }

    if ($type instanceof UnionType) {
        foreach ($type->types as $inner) {
            if ($inner instanceof InertiaRender) {
                return $inner;
            }
        }
    }

    return null;
}

describe('Inertia special prop types', function () {
    it('resolves Inertia::defer() to the callback return type', function () {
        $fixture = createPhpFixture('
namespace App\\Test;

use Inertia\\Inertia;
use App\\Models\\User;

class DashboardController
{
    public function index()
    {
        return Inertia::render(\'Dashboard\', [
            \'users\' => Inertia::defer(fn () => User::all()),
        ]);
    }
}');

        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyze($fixture)->result();

        expect($result)->toBeInstanceOf(ClassLikeResult::class);

        $render = findInertiaRender($result->getMethod('index')->returnType());

        expect($render)->not->toBeNull();
        expect($render->data->value['users'])->toBeInstanceOf(ClassType::class);
        expect($render->data->value['users']->value)->toBe('Illuminate\\Database\\Eloquent\\Collection');
        expect($render->data->value['users']->isOptional())->toBeTrue();

        unlink($fixture);
    });

    it('resolves Inertia::optional() to the callback return type marked optional', function () {
        $fixture = createPhpFixture('
namespace App\\Test;

use Inertia\\Inertia;

class DashboardController
{
    public function index()
    {
        return Inertia::render(\'Dashboard\', [
            \'name\' => Inertia::optional(fn () => \'hello\'),
        ]);
    }
}');

        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyze($fixture)->result();

        $render = findInertiaRender($result->getMethod('index')->returnType());

        expect($render)->not->toBeNull();
        expect($render->data->value['name'])->toBeInstanceOf(StringType::class);
        expect($render->data->value['name']->isOptional())->toBeTrue();

        unlink($fixture);
    });

    it('resolves Inertia::lazy() to the callback return type marked optional', function () {
        $fixture = createPhpFixture('
namespace App\\Test;

use Inertia\\Inertia;

class DashboardController
{
    public function index()
    {
        return Inertia::render(\'Dashboard\', [
            \'count\' => Inertia::lazy(fn () => 42),
        ]);
    }
}');

        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyze($fixture)->result();

        $render = findInertiaRender($result->getMethod('index')->returnType());

        expect($render)->not->toBeNull();
        expect($render->data->value['count'])->toBeInstanceOf(IntType::class);
        expect($render->data->value['count']->isOptional())->toBeTrue();

        unlink($fixture);
    });

    it('resolves Inertia::always() to the callback return type', function () {
        $fixture = createPhpFixture('
namespace App\\Test;

use Inertia\\Inertia;

class DashboardController
{
    public function index()
    {
        return Inertia::render(\'Dashboard\', [
            \'name\' => Inertia::always(fn () => \'hello\'),
        ]);
    }
}');

        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyze($fixture)->result();

        $render = findInertiaRender($result->getMethod('index')->returnType());

        expect($render)->not->toBeNull();
        expect($render->data->value['name'])->toBeInstanceOf(StringType::class);
        expect($render->data->value['name']->isOptional())->toBeFalse();

        unlink($fixture);
    });

    it('resolves Inertia::merge() to the callback return type', function () {
        $fixture = createPhpFixture('
namespace App\\Test;

use Inertia\\Inertia;
use App\\Models\\User;

class DashboardController
{
    public function index()
    {
        return Inertia::render(\'Dashboard\', [
            \'users\' => Inertia::merge(fn () => User::all()),
        ]);
    }
}');

        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyze($fixture)->result();

        $render = findInertiaRender($result->getMethod('index')->returnType());

        expect($render)->not->toBeNull();
        expect($render->data->value['users'])->toBeInstanceOf(ClassType::class);
        expect($render->data->value['users']->value)->toBe('Illuminate\\Database\\Eloquent\\Collection');
        expect($render->data->value['users']->isOptional())->toBeFalse();

        unlink($fixture);
    });
});
