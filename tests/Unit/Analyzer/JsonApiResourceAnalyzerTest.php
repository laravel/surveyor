<?php

use App\Http\Resources\JsonApi\PostApiResource;
use App\Http\Resources\JsonApi\UserApiResource;
use App\Http\Resources\PostResource;
use Laravel\Surveyor\Analyzer\AnalyzedCache;
use Laravel\Surveyor\Analyzer\Analyzer;
use Laravel\Surveyor\Analyzer\ResourceAnalyzer;
use Laravel\Surveyor\Types\ArrayType;
use Laravel\Surveyor\Types\Entities\JsonApiResourceResponse;
use Laravel\Surveyor\Types\Entities\ResourceResponse;

uses()->group('integration');

beforeEach(function () {
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
});
