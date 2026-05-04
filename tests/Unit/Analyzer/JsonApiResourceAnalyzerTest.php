<?php

use App\Http\Resources\JsonApi\ChildJsonApiResource;
use App\Http\Resources\JsonApi\EmptyShapeApiResource;
use App\Http\Resources\JsonApi\PostApiResource;
use App\Http\Resources\JsonApi\UserApiResource;
use App\Http\Resources\PostResource;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Laravel\Surveyor\Analyzer\AnalyzedCache;
use Laravel\Surveyor\Analyzer\Analyzer;
use Laravel\Surveyor\Analyzer\ResourceAnalyzer;
use Laravel\Surveyor\Types\ArrayShapeType;
use Laravel\Surveyor\Types\ArrayType;
use Laravel\Surveyor\Types\Entities\JsonApiResourceResponse;
use Laravel\Surveyor\Types\Entities\ResourceResponse;
use Laravel\Surveyor\Types\StringType;

uses()->group('integration');

beforeEach(function () {
    if (! class_exists(JsonApiResource::class)) {
        $this->markTestSkipped('JsonApiResource requires Laravel 13+');
    }

    AnalyzedCache::clear();
});

afterEach(function () {
    AnalyzedCache::clear();
});

describe('JSON:API ResourceAnalyzer', function () {
    it('detects JSON:API resource with $attributes property', function () {
        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyzeClass(PostApiResource::class)->result();

        expect($result)->not->toBeNull();

        $response = $result->resourceResponse();
        expect($response)->toBeInstanceOf(JsonApiResourceResponse::class);
        expect($response->attributes)->toBeInstanceOf(ArrayType::class);
        expect($response->attributes->keys())->toContain('title');
        expect($response->attributes->keys())->toContain('body');
        expect($response->isCollection)->toBeFalse();
    });

    it('detects JSON:API resource with $relationships property', function () {
        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyzeClass(PostApiResource::class)->result();

        $response = $result->resourceResponse();
        expect($response)->toBeInstanceOf(JsonApiResourceResponse::class);
        expect($response->relationships)->toBeInstanceOf(ArrayType::class);
        expect($response->relationships->keys())->toContain('user');

        // Each relationship resolves to the JSON:API identifier shape:
        // { data: { id: string, type: string } | null }
        $user = $response->relationships->value['user'];
        expect($user)->toBeInstanceOf(ArrayType::class);
        expect($user->keys())->toEqual(['data']);

        $data = $user->value['data'];
        expect($data)->toBeInstanceOf(ArrayType::class);
        expect($data->isNullable())->toBeTrue();
        expect($data->keys())->toEqual(['id', 'type']);
        expect($data->value['id'])->toBeInstanceOf(StringType::class);
        expect($data->value['type'])->toBeInstanceOf(StringType::class);
    });

    it('detects JSON:API resource with toAttributes() method', function () {
        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyzeClass(UserApiResource::class)->result();

        $response = $result->resourceResponse();
        expect($response)->toBeInstanceOf(JsonApiResourceResponse::class);
        expect($response->attributes)->toBeInstanceOf(ArrayType::class);
        expect($response->attributes->keys())->toContain('name');
        expect($response->attributes->keys())->toContain('email');
    });

    it('detects JSON:API resource with toRelationships() method', function () {
        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyzeClass(UserApiResource::class)->result();

        $response = $result->resourceResponse();
        expect($response)->toBeInstanceOf(JsonApiResourceResponse::class);
        expect($response->relationships)->toBeInstanceOf(ArrayType::class);
        expect($response->relationships->keys())->toContain('posts');

        // toRelationships() values must be wrapped into identifier shapes,
        // same as the $relationships property form. User::posts is hasMany, so
        // the shape is { data: [{ id, type }] } (array of identifiers).
        $posts = $response->relationships->value['posts'];
        expect($posts)->toBeInstanceOf(ArrayType::class);
        expect($posts->keys())->toEqual(['data']);

        $data = $posts->value['data'];
        expect($data)->toBeInstanceOf(ArrayShapeType::class);
        expect($data->valueType)->toBeInstanceOf(ArrayType::class);
        expect($data->valueType->keys())->toEqual(['id', 'type']);
    });

    it('captures toLinks() data', function () {
        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyzeClass(UserApiResource::class)->result();

        $response = $result->resourceResponse();
        expect($response)->toBeInstanceOf(JsonApiResourceResponse::class);
        expect($response->links)->toBeInstanceOf(ArrayType::class);
        expect($response->links->keys())->toContain('self');
    });

    it('captures toMeta() data', function () {
        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyzeClass(UserApiResource::class)->result();

        $response = $result->resourceResponse();
        expect($response)->toBeInstanceOf(JsonApiResourceResponse::class);
        expect($response->meta)->toBeInstanceOf(ArrayType::class);
        expect($response->meta->keys())->toContain('created_at');
    });

    it('captures with() method data as additional', function () {
        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyzeClass(UserApiResource::class)->result();

        $response = $result->resourceResponse();
        expect($response)->toBeInstanceOf(JsonApiResourceResponse::class);
        expect($response->additional)->toBeInstanceOf(ArrayType::class);
        expect($response->additional->keys())->toContain('jsonapi');
    });

    it('builds JsonApiResourceResponse for external use', function () {
        $resourceAnalyzer = app(ResourceAnalyzer::class);

        $response = $resourceAnalyzer->buildResourceResponse(PostApiResource::class);
        expect($response)->toBeInstanceOf(JsonApiResourceResponse::class);
        expect($response->attributes)->toBeInstanceOf(ArrayType::class);
        expect($response->isCollection)->toBeFalse();
    });

    it('builds collection JsonApiResourceResponse', function () {
        $resourceAnalyzer = app(ResourceAnalyzer::class);

        $response = $resourceAnalyzer->buildResourceResponse(PostApiResource::class, isCollection: true);
        expect($response)->toBeInstanceOf(JsonApiResourceResponse::class);
        expect($response->isCollection)->toBeTrue();
    });

    it('does not interfere with standard JsonResource analysis', function () {
        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyzeClass(PostResource::class)->result();

        $response = $result->resourceResponse();
        expect($response)->toBeInstanceOf(ResourceResponse::class);
        expect($response)->not->toBeInstanceOf(JsonApiResourceResponse::class);
    });

    it('preserves empty toLinks/toMeta shapes instead of dropping them', function () {
        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyzeClass(EmptyShapeApiResource::class)->result();

        $response = $result->resourceResponse();
        expect($response)->toBeInstanceOf(JsonApiResourceResponse::class);

        // Explicit `return []` is the user signaling "no links/meta yet"; the type
        // should reflect that as an empty shape, not be silently nulled out.
        expect($response->links)->toBeInstanceOf(ArrayType::class);
        expect($response->links->value)->toBe([]);
        expect($response->meta)->toBeInstanceOf(ArrayType::class);
        expect($response->meta->value)->toBe([]);
    });

    it('honors $attributes and $relationships inherited from a base resource', function () {
        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyzeClass(ChildJsonApiResource::class)->result();

        $response = $result->resourceResponse();
        expect($response)->toBeInstanceOf(JsonApiResourceResponse::class);

        expect($response->attributes)->toBeInstanceOf(ArrayType::class);
        expect($response->attributes->keys())->toContain('title');
        expect($response->attributes->keys())->toContain('body');

        expect($response->relationships)->toBeInstanceOf(ArrayType::class);
        expect($response->relationships->keys())->toContain('user');
    });
});
