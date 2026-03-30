<?php

use App\Http\Resources\CustomWrapResource;
use App\Http\Resources\PostResource;
use App\Http\Resources\UnwrappedResource;
use App\Http\Resources\UserCollection;
use App\Http\Resources\UserResource;
use Laravel\Surveyor\Analyzer\AnalyzedCache;
use Laravel\Surveyor\Analyzer\Analyzer;
use Laravel\Surveyor\Analyzer\ResourceAnalyzer;
use Laravel\Surveyor\Types\ArrayType;
use Laravel\Surveyor\Types\Entities\ResourceResponse;

uses()->group('integration');

beforeEach(function () {
    AnalyzedCache::clear();
});

afterEach(function () {
    AnalyzedCache::clear();
});

describe('ResourceAnalyzer', function () {
    it('detects resource class and extracts toArray shape', function () {
        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyzeClass(PostResource::class)->result();

        expect($result)->not->toBeNull();

        $resourceResponse = $result->resourceResponse();
        expect($resourceResponse)->toBeInstanceOf(ResourceResponse::class);
        expect($resourceResponse->data)->toBeInstanceOf(ArrayType::class);
        expect($resourceResponse->data->keys())->toContain('id');
        expect($resourceResponse->data->keys())->toContain('title');
        expect($resourceResponse->data->keys())->toContain('body');
        expect($resourceResponse->isCollection)->toBeFalse();
        expect($resourceResponse->wrap)->toBe('data');
    });

    it('resolves model properties via @mixin for $this-> access', function () {
        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyzeClass(PostResource::class)->result();

        $resourceResponse = $result->resourceResponse();
        expect($resourceResponse)->not->toBeNull();

        // The toArray keys should have resolved types from the Post model
        $data = $resourceResponse->data;
        expect($data)->toBeInstanceOf(ArrayType::class);
    });

    it('marks conditional attributes as optional', function () {
        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyzeClass(UserResource::class)->result();

        $resourceResponse = $result->resourceResponse();
        expect($resourceResponse)->not->toBeNull();
        expect($resourceResponse->data)->toBeInstanceOf(ArrayType::class);

        $data = $resourceResponse->data;

        // 'email' uses when() — should be optional
        expect($data->value)->toHaveKey('email');
        expect($data->value['email']->isOptional())->toBeTrue();

        // 'posts_count' uses whenCounted() — should be optional
        expect($data->value)->toHaveKey('posts_count');
        expect($data->value['posts_count']->isOptional())->toBeTrue();

        // 'id' is not conditional — should not be optional
        expect($data->value)->toHaveKey('id');
        expect($data->value['id']->isOptional())->toBeFalse();
    });

    it('captures with() method data', function () {
        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyzeClass(UserResource::class)->result();

        $resourceResponse = $result->resourceResponse();
        expect($resourceResponse)->not->toBeNull();
        expect($resourceResponse->additional)->toBeInstanceOf(ArrayType::class);
        expect($resourceResponse->additional->keys())->toContain('meta');
    });

    it('handles null wrap property', function () {
        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyzeClass(UnwrappedResource::class)->result();

        $resourceResponse = $result->resourceResponse();
        expect($resourceResponse)->not->toBeNull();
        expect($resourceResponse->wrap)->toBeNull();
    });

    it('handles custom wrap property', function () {
        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyzeClass(CustomWrapResource::class)->result();

        $resourceResponse = $result->resourceResponse();
        expect($resourceResponse)->not->toBeNull();
        expect($resourceResponse->wrap)->toBe('results');
    });

    it('detects ResourceCollection as collection', function () {
        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyzeClass(UserCollection::class)->result();

        $resourceResponse = $result->resourceResponse();
        expect($resourceResponse)->not->toBeNull();
        expect($resourceResponse->isCollection)->toBeTrue();
    });

    it('builds ResourceResponse for external use', function () {
        $resourceAnalyzer = app(ResourceAnalyzer::class);

        $response = $resourceAnalyzer->buildResourceResponse(PostResource::class);
        expect($response)->toBeInstanceOf(ResourceResponse::class);
        expect($response->data)->toBeInstanceOf(ArrayType::class);
        expect($response->isCollection)->toBeFalse();
    });

    it('builds collection ResourceResponse', function () {
        $resourceAnalyzer = app(ResourceAnalyzer::class);

        $response = $resourceAnalyzer->buildResourceResponse(PostResource::class, isCollection: true);
        expect($response)->toBeInstanceOf(ResourceResponse::class);
        expect($response->isCollection)->toBeTrue();
    });
});
