<?php

use App\Http\Resources\MetadataTagResource;
use App\Http\Resources\UserResource;
use Laravel\Surveyor\Analyzed\ClassLikeResult;
use Laravel\Surveyor\Analyzer\AnalyzedCache;
use Laravel\Surveyor\Analyzer\Analyzer;
use Laravel\Surveyor\Analyzer\ResourceAnalyzer;
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
    it('merges fluent additional metadata without mutating the resource shape', function () {
        $fixture = createPhpFixture('
namespace App\\Test;

use App\\Http\\Resources\\MetadataTagResource;
use App\\Models\\Tag;

class MetadataController
{
    public function show(Tag $tag)
    {
        return MetadataTagResource::make($tag)
            ->additional(["removed" => true])
            ->additional(["meta" => ["source" => "controller", "count" => 1]]);
    }
}');

        try {
            $response = app(Analyzer::class)->analyze($fixture)->result()->getMethod('show')->returnType();

            expect($response)->toBeInstanceOf(ResourceResponse::class)
                ->and($response->additional->value)->not->toHaveKey('removed')
                ->and($response->additional->value['meta']->keys())->toBe(['version', 'source', 'count'])
                ->and($response->additional->value['meta']->value['source']->value)->toHaveCount(2);

            $plain = app(ResourceAnalyzer::class)->buildResourceResponse(MetadataTagResource::class);

            expect($plain->additional->value['meta']->keys())->toBe(['version', 'source'])
                ->and($response->id())->not->toBe($plain->id());
        } finally {
            unlink($fixture);
        }
    });

    it('does not make a resource nullable because its input is nullable', function () {
        $fixture = createPhpFixture('
namespace App\\Test;

use App\\Http\\Resources\\PlainLabelResource;
use App\\Models\\Tag;

class NullableController
{
    public function show(?Tag $tag)
    {
        return PlainLabelResource::make($tag);
    }
}');

        try {
            $response = app(Analyzer::class)->analyze($fixture)->result()->getMethod('show')->returnType();

            expect($response)->toBeInstanceOf(ResourceResponse::class)
                ->and($response->isNullable())->toBeFalse();
        } finally {
            unlink($fixture);
        }
    });

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

describe('Resource response method calls', function () {
    it('keeps the resource response through fluent collection and response methods', function (string $call) {
        $fixture = createPhpFixture('
namespace App\\Test;

use App\\Http\\Resources\\PlainLabelResource;
use App\\Models\\Tag;

class FluentResourceController
{
    public function index()
    {
        return PlainLabelResource::collection(Tag::query()->paginate(10))'.$call.';
    }
}');

        try {
            $response = app(Analyzer::class)->analyze($fixture)->result()->getMethod('index')->returnType();

            expect($response)->toBeInstanceOf(ResourceResponse::class)
                ->and($response->isCollection)->toBeTrue()
                ->and($response->pagination)->not->toBeNull();
        } finally {
            unlink($fixture);
        }
    })->with([
        'preserveQuery()' => ['->preserveQuery()'],
        'withQuery()' => ['->withQuery(["sort" => "label"])'],
        'response()' => ['->response()'],
        'response() with a status' => ['->response()->setStatusCode(201)'],
        'response() with headers' => ['->response()->header("X-Total", "1")->withHeaders([])'],
    ]);

    it('records preserved keys set on the collection', function (string $call, bool $preserved) {
        $fixture = createPhpFixture('
namespace App\\Test;

use App\\Http\\Resources\\KeyedPropertyLabelResource;

class KeyedResourceController
{
    public function index()
    {
        return KeyedPropertyLabelResource::collection([])'.$call.';
    }
}');

        try {
            $response = app(Analyzer::class)->analyze($fixture)->result()->getMethod('index')->returnType();

            expect($response)->toBeInstanceOf(ResourceResponse::class)
                ->and($response->preservesKeys())->toBe($preserved);
        } finally {
            unlink($fixture);
        }
    })->with([
        'resource default' => ['', true],
        'turned off' => ['->preserveKeys(false)', false],
        'turned back on' => ['->preserveKeys(false)->preserveKeys()', true],
    ]);
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
