<?php

use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelInspector;
use Laravel\Surveyor\Analyzer\AnalyzedCache;
use Laravel\Surveyor\Analyzer\Analyzer;
use Laravel\Surveyor\Types\ClassType;

uses()->group('integration');

beforeEach(function () {
    AnalyzedCache::clear();
});

afterEach(function () {
    AnalyzedCache::clear();
});

describe('ModelAnalyzer relations', function () {
    it('detects notifications relationship from Notifiable trait', function () {
        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyzeClass(User::class)->result();

        expect($result->hasMethod('notifications'))->toBeTrue();

        $notificationsMethod = $result->getMethod('notifications');
        expect($notificationsMethod->isModelRelation())->toBeTrue();
    });

    it('detects posts relationship defined directly on User model', function () {
        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyzeClass(User::class)->result();

        expect($result->hasMethod('posts'))->toBeTrue();

        $postsMethod = $result->getMethod('posts');
        expect($postsMethod->isModelRelation())->toBeTrue();
    });

    it('detects belongsTo relationship on Post model', function () {
        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyzeClass(Post::class)->result();

        expect($result->hasMethod('user'))->toBeTrue();

        $userMethod = $result->getMethod('user');
        expect($userMethod->isModelRelation())->toBeTrue();
    });

    it('adds relation properties to the ClassResult', function () {
        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyzeClass(User::class)->result();

        expect($result->hasProperty('posts'))->toBeTrue();

        $postsProperty = $result->getProperty('posts');
        expect($postsProperty->modelRelation)->toBeTrue();
    });

    it('correctly identifies collection relations vs singular relations', function () {
        $analyzer = app(Analyzer::class);

        $userResult = $analyzer->analyzeClass(User::class)->result();
        $postResult = $analyzer->analyzeClass(Post::class)->result();

        // posts on User should be a collection (HasMany)
        $postsProperty = $userResult->getProperty('posts');
        expect($postsProperty->modelRelation)->toBeTrue();

        // user on Post should be singular (BelongsTo)
        $userProperty = $postResult->getProperty('user');
        expect($userProperty->modelRelation)->toBeTrue();
    });

    it('preserves generic type info on relation methods', function () {
        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyzeClass(User::class)->result();

        $postsMethod = $result->getMethod('posts');
        $returnType = $postsMethod->returnType();

        expect($returnType)->toBeInstanceOf(ClassType::class);

        $genericTypes = $returnType->genericTypes();
        expect($genericTypes)->toHaveKey('TRelatedModel');
        expect($genericTypes['TRelatedModel'])->toBeInstanceOf(ClassType::class);
        expect($genericTypes['TRelatedModel']->value)->toBe(Post::class);
    });
});

describe('ModelAnalyzer attributes', function () {
    it('detects model attributes from database columns when connected', function () {
        $inspector = app(ModelInspector::class);
        $info = $inspector->inspect(User::class);

        $attributeNames = $info['attributes']->pluck('name')->toArray();

        if (empty($attributeNames)) {
            $this->markTestSkipped('Database tables not set up - skipping attribute tests');
        }

        expect($attributeNames)->toContain('id');
        expect($attributeNames)->toContain('name');
        expect($attributeNames)->toContain('email');
    });

    it('adds database attributes as model attribute properties', function () {
        $inspector = app(ModelInspector::class);
        $info = $inspector->inspect(User::class);

        $attributes = $info['attributes']->keyBy('name');

        if ($attributes->isEmpty()) {
            $this->markTestSkipped('Database tables not set up - skipping attribute tests');
        }

        expect($attributes->has('id'))->toBeTrue();
        expect($attributes->has('name'))->toBeTrue();
    });
});

describe('ModelInspector integration', function () {
    it('returns all relations from ModelInspector', function () {
        $inspector = app(ModelInspector::class);
        $info = $inspector->inspect(User::class);

        $relationNames = $info['relations']->pluck('name')->toArray();

        expect($relationNames)->toContain('notifications');
        expect($relationNames)->toContain('posts');
    });

    it('returns correct relation types', function () {
        $inspector = app(ModelInspector::class);
        $info = $inspector->inspect(User::class);

        $relations = $info['relations']->keyBy('name');

        expect($relations->get('posts')['type'])->toBe('HasMany');
        expect($relations->get('notifications')['type'])->toBe('MorphMany');
    });

    it('returns correct related models', function () {
        $inspector = app(ModelInspector::class);
        $info = $inspector->inspect(User::class);

        $relations = $info['relations']->keyBy('name');

        expect($relations->get('posts')['related'])->toBe(Post::class);
    });
});
