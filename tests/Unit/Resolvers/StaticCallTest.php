<?php

use App\Http\Resources\UserResource;
use Laravel\Surveyor\Analyzed\ClassLikeResult;
use Laravel\Surveyor\Analyzer\AnalyzedCache;
use Laravel\Surveyor\Analyzer\Analyzer;
use Laravel\Surveyor\Types\ClassType;
use Laravel\Surveyor\Types\Contracts\Type as TypeContract;
use Laravel\Surveyor\Types\Entities\ResourceResponse;
use Laravel\Surveyor\Types\IntType;
use Laravel\Surveyor\Types\UnionType;

uses()->group('integration');

beforeEach(function () {
    AnalyzedCache::clear();
});

afterEach(function () {
    AnalyzedCache::clear();
});

function findResourceResponse(TypeContract $type): ?ResourceResponse
{
    if ($type instanceof ResourceResponse) {
        return $type;
    }

    if ($type instanceof UnionType) {
        foreach ($type->types as $inner) {
            if ($inner instanceof ResourceResponse) {
                return $inner;
            }
        }
    }

    return null;
}

describe('StaticCall resolver', function () {
    it('resolves Resource::make($model) to a ResourceResponse', function () {
        $fixture = createPhpFixture('
namespace App\\Test;

use App\\Http\\Resources\\UserResource;
use App\\Models\\User;

class MakeController
{
    public function show(User $user)
    {
        return UserResource::make($user);
    }
}');

        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyze($fixture)->result();

        expect($result)->toBeInstanceOf(ClassLikeResult::class);

        $returnType = $result->getMethod('show')->returnType();
        $response = findResourceResponse($returnType);

        expect($response)->not->toBeNull();
        expect($response)->toBeInstanceOf(ResourceResponse::class);
        expect($response->isCollection)->toBeFalse();
        expect($response->resourceClass)->toBe(UserResource::class);
        expect($response->data->keys())->toContain('id');
        expect($response->data->keys())->toContain('name');

        unlink($fixture);
    });

    it('resolves Resource::collection($models) to a collection ResourceResponse', function () {
        $fixture = createPhpFixture('
namespace App\\Test;

use App\\Http\\Resources\\UserResource;
use App\\Models\\User;

class CollectionController
{
    public function index()
    {
        return UserResource::collection(User::all());
    }
}');

        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyze($fixture)->result();

        $returnType = $result->getMethod('index')->returnType();
        $response = findResourceResponse($returnType);

        expect($response)->not->toBeNull();
        expect($response->isCollection)->toBeTrue();
        expect($response->resourceClass)->toBe(UserResource::class);

        unlink($fixture);
    });

    it('does not pollute Resource::collection() return type with the documented AnonymousResourceCollection class', function () {
        $fixture = createPhpFixture('
namespace App\\Test;

use App\\Http\\Resources\\UserResource;
use App\\Models\\User;

class CollectionController
{
    public function index()
    {
        return UserResource::collection(User::all());
    }
}');

        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyze($fixture)->result();

        $returnType = $result->getMethod('index')->returnType();

        // The ResourceResponse fully describes this call — the documented
        // Illuminate\Http\Resources\Json\AnonymousResourceCollection return
        // shouldn't get unioned in alongside it.
        expect($returnType)->toBeInstanceOf(ResourceResponse::class);

        unlink($fixture);
    });
});

describe('Eloquent builder chains', function () {
    it('resolves Model::query()->count() to int', function () {
        $fixture = createPhpFixture('
namespace App\\Test;

use App\\Models\\User;

class UserController
{
    public function index()
    {
        $count = User::query()->count();

        return $count;
    }
}');

        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyze($fixture)->result();

        $returnType = $result->getMethod('index')->returnType();

        expect($returnType)->toBeInstanceOf(IntType::class);

        unlink($fixture);
    });

    it('resolves Model::query()->firstWhere() to Model|null', function () {
        $fixture = createPhpFixture('
namespace App\\Test;

use App\\Models\\Post;

class PostController
{
    public function show()
    {
        return Post::query()->firstWhere(\'id\', 1);
    }
}');

        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyze($fixture)->result();

        $returnType = $result->getMethod('show')->returnType();

        expect($returnType)->toBeInstanceOf(ClassType::class);
        expect($returnType->value)->toBe('App\\Models\\Post');
        expect($returnType->nullable)->toBeTrue();

        unlink($fixture);
    });

    it('resolves Model::query()->get() to Collection of Model', function () {
        $fixture = createPhpFixture('
namespace App\\Test;

use App\\Models\\Post;

class PostController
{
    public function index()
    {
        return Post::query()->get();
    }
}');

        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyze($fixture)->result();

        $returnType = $result->getMethod('index')->returnType();

        expect($returnType)->toBeInstanceOf(ClassType::class);
        expect($returnType->value)->toBe('Illuminate\\Database\\Eloquent\\Collection');
        expect($returnType->genericTypes())->toHaveCount(2);

        $modelType = $returnType->genericTypes()[1];

        expect($modelType)->toBeInstanceOf(ClassType::class);
        expect($modelType->value)->toBe('App\\Models\\Post');

        unlink($fixture);
    });

    it('preserves generic type through fluent method chain', function () {
        $fixture = createPhpFixture('
namespace App\\Test;

use App\\Models\\Post;

class PostController
{
    public function show()
    {
        return Post::query()->with(\'comments\')->where(\'active\', 1)->firstWhere(\'id\', 1);
    }
}');

        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyze($fixture)->result();

        $returnType = $result->getMethod('show')->returnType();

        expect($returnType)->toBeInstanceOf(ClassType::class);
        expect($returnType->value)->toBe('App\\Models\\Post');
        expect($returnType->nullable)->toBeTrue();

        unlink($fixture);
    });

    it('resolves Model::query()->first() to Model|null via @use trait binding', function () {
        $fixture = createPhpFixture('
namespace App\\Test;

use App\\Models\\Post;

class PostController
{
    public function show()
    {
        return Post::query()->first();
    }
}');

        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyze($fixture)->result();

        $returnType = $result->getMethod('show')->returnType();

        expect($returnType)->toBeInstanceOf(ClassType::class);
        expect($returnType->value)->toBe('App\\Models\\Post');
        expect($returnType->nullable)->toBeTrue();

        unlink($fixture);
    });
});
