<?php

use Laravel\Surveyor\Analyzed\ClassLikeResult;
use Laravel\Surveyor\Analyzer\AnalyzedCache;
use Laravel\Surveyor\Analyzer\Analyzer;
use Laravel\Surveyor\Types\ClassType;
use Laravel\Surveyor\Types\Contracts\Type as TypeContract;
use Laravel\Surveyor\Types\Entities\InertiaProp;
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
        expect($render->data->value['users'])->toBeInstanceOf(InertiaProp::class);
        expect($render->data->value['users']->data)->toBeInstanceOf(ClassType::class);
        expect($render->data->value['users']->data->value)->toBe('Illuminate\\Database\\Eloquent\\Collection');
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
        expect($render->data->value['name'])->toBeInstanceOf(InertiaProp::class);
        expect($render->data->value['name']->data)->toBeInstanceOf(StringType::class);
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
        expect($render->data->value['count'])->toBeInstanceOf(InertiaProp::class);
        expect($render->data->value['count']->data)->toBeInstanceOf(IntType::class);
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
        expect($render->data->value['name'])->toBeInstanceOf(InertiaProp::class);
        expect($render->data->value['name']->data)->toBeInstanceOf(StringType::class);
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
        expect($render->data->value['users'])->toBeInstanceOf(InertiaProp::class);
        expect($render->data->value['users']->data)->toBeInstanceOf(ClassType::class);
        expect($render->data->value['users']->data->value)->toBe('Illuminate\\Database\\Eloquent\\Collection');
        expect($render->data->value['users']->isOptional())->toBeFalse();

        unlink($fixture);
    });
});

describe('Inertia component names', function () {
    it('skips the page type when the component name is not fully known', function () {
        $fixture = createPhpFixture('
namespace App\\Test;

use Illuminate\\Support\\Str;
use Inertia\\Inertia;

class ProviderModalController
{
    public function staticCall(string $provider)
    {
        $modal = Str::of($provider)->studly()->toString();

        return Inertia::render(\'Modals/Providers/\'.$modal, [\'provider\' => $provider]);
    }

    public function helper(string $provider)
    {
        $modal = Str::of($provider)->studly()->toString();

        return inertia(\'Modals/Providers/\'.$modal, [\'provider\' => $provider]);
    }

    public function literal()
    {
        return Inertia::render(\'Modals/\'.\'Providers\', []);
    }
}');

        $result = app(Analyzer::class)->analyze($fixture)->result();

        expect(findInertiaRender($result->getMethod('staticCall')->returnType()))->toBeNull();
        expect(findInertiaRender($result->getMethod('helper')->returnType()))->toBeNull();
        expect(findInertiaRender($result->getMethod('literal')->returnType())->view)->toBe('Modals/Providers');
    });
});

describe('Inertia prop chaining', function () {
    it('keeps the value through chained configuration and handles once, deepMerge and scroll', function () {
        $fixture = createPhpFixture('
namespace App\\Test;

use Inertia\\Inertia;

class ChainedPropsController
{
    public function index()
    {
        return Inertia::render(\'Chained\', [
            \'auth\' => Inertia::once(fn () => [\'name\' => \'taylor\'])->fresh(true),
            \'feed\' => Inertia::deepMerge([\'items\' => 1]),
            \'posts\' => Inertia::defer(fn () => 5)->merge(),
            \'page\' => Inertia::scroll(fn () => \'cursor\'),
        ]);
    }
}');

        $render = findInertiaRender(app(Analyzer::class)->analyze($fixture)->result()->getMethod('index')->returnType());

        expect($render->data->value['auth'])->toBeInstanceOf(InertiaProp::class);
        expect($render->data->value['auth']->data->value['name']->value)->toBe('taylor');
        expect($render->data->value['feed']->data->value['items']->value)->toBe(1);
        expect($render->data->value['posts']->data)->toBeInstanceOf(IntType::class);
        expect($render->data->value['posts']->isOptional())->toBeTrue();
        expect($render->data->value['page']->data->value)->toBe('cursor');
    });
});
