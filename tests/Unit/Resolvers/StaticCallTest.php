<?php

use App\Http\Resources\UserResource;
use Laravel\Surveyor\Analyzed\ClassResult;
use Laravel\Surveyor\Analyzer\AnalyzedCache;
use Laravel\Surveyor\Analyzer\Analyzer;
use Laravel\Surveyor\Types\Contracts\Type as TypeContract;
use Laravel\Surveyor\Types\Entities\ResourceResponse;
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

        expect($result)->toBeInstanceOf(ClassResult::class);

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
});
