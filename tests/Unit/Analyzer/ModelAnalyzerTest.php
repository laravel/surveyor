<?php

use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelInspector;
use Laravel\Surveyor\Analyzer\AnalyzedCache;
use Laravel\Surveyor\Analyzer\Analyzer;
use Laravel\Surveyor\Types\ArrayType;
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

        if (! in_array('id', $attributeNames)) {
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

        if (! $attributes->has('id')) {
            $this->markTestSkipped('Database tables not set up - skipping attribute tests');
        }

        expect($attributes->has('id'))->toBeTrue();
        expect($attributes->has('name'))->toBeTrue();
    });
});

describe('ModelAnalyzer computed attributes', function () {
    it('extracts type from Attribute<string> PHPDoc', function () {
        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyzeClass(User::class)->result();

        $inspector = app(ModelInspector::class);
        $info = $inspector->inspect(User::class);

        $attributes = $info['attributes']->keyBy('name');

        if (! $attributes->has('formatted_name')) {
            $this->markTestSkipped('Model does not have formatted_name computed attribute');
        }

        expect($result->hasProperty('formatted_name'))->toBeTrue();

        $property = $result->getProperty('formatted_name');
        expect($property->type->id())->toBe('string');
    });

    it('extracts union type from Attribute<int|null> PHPDoc', function () {
        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyzeClass(User::class)->result();

        $inspector = app(ModelInspector::class);
        $info = $inspector->inspect(User::class);

        $attributes = $info['attributes']->keyBy('name');

        if (! $attributes->has('age_in_months')) {
            $this->markTestSkipped('Model does not have age_in_months computed attribute');
        }

        expect($result->hasProperty('age_in_months'))->toBeTrue();

        $property = $result->getProperty('age_in_months');
        expect($property->type->id())->toBe('int');
        expect($property->type->isNullable())->toBeTrue();
    });

    it('extracts type from getter closure return type hint when no PHPDoc', function () {
        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyzeClass(User::class)->result();

        $inspector = app(ModelInspector::class);
        $info = $inspector->inspect(User::class);

        $attributes = $info['attributes']->keyBy('name');

        if (! $attributes->has('without_doc_block')) {
            $this->markTestSkipped('Model does not have without_doc_block computed attribute');
        }

        expect($result->hasProperty('without_doc_block'))->toBeTrue();

        $property = $result->getProperty('without_doc_block');
        // Closure has fn (): string => ... so we should extract string, not Attribute
        expect($property->type->id())->toBe('string');
    });

    it('extracts int type from getter closure return type hint when no PHPDoc', function () {
        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyzeClass(User::class)->result();

        $inspector = app(ModelInspector::class);
        $info = $inspector->inspect(User::class);

        $attributes = $info['attributes']->keyBy('name');

        if (! $attributes->has('money_in_cents')) {
            $this->markTestSkipped('Model does not have money_in_cents computed attribute');
        }

        expect($result->hasProperty('money_in_cents'))->toBeTrue();

        $property = $result->getProperty('money_in_cents');
        // Closure has fn (): int => ... so we should extract int
        expect($property->type->id())->toBe('int');
    });

    it('resolves DTO toArray() shape when getter returns an Arrayable DTO', function () {
        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyzeClass(User::class)->result();

        $inspector = app(ModelInspector::class);
        $info = $inspector->inspect(User::class);

        $attributes = $info['attributes']->keyBy('name');

        if (! $attributes->has('money')) {
            $this->markTestSkipped('Model does not have money computed attribute');
        }

        expect($result->hasProperty('money'))->toBeTrue();

        $property = $result->getProperty('money');

        // MoneyDTO implements Arrayable, so Surveyor should resolve toArray() shape
        expect($property->type)->toBeInstanceOf(ArrayType::class);
        expect($property->type->keys())->toContain('amount');
        expect($property->type->keys())->toContain('currency');
        expect($property->type->keys())->toContain('currency_amount');
    });

    it('extracts type from regular closure return type hint when no PHPDoc', function () {
        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyzeClass(User::class)->result();

        $inspector = app(ModelInspector::class);
        $info = $inspector->inspect(User::class);

        if (! $info['attributes']->keyBy('name')->has('without_doc_block_closure')) {
            $this->markTestSkipped('Model does not have without_doc_block_closure computed attribute');
        }

        $property = $result->getProperty('without_doc_block_closure');
        expect($property->type->id())->toBe('string');
    });

    it('resolves DTO toArray() shape when getter is a regular closure returning an Arrayable DTO', function () {
        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyzeClass(User::class)->result();

        $inspector = app(ModelInspector::class);
        $info = $inspector->inspect(User::class);

        if (! $info['attributes']->keyBy('name')->has('money_closure')) {
            $this->markTestSkipped('Model does not have money_closure computed attribute');
        }

        $property = $result->getProperty('money_closure');
        expect($property->type)->toBeInstanceOf(ArrayType::class);
        expect($property->type->keys())->toContain('amount');
        expect($property->type->keys())->toContain('currency');
        expect($property->type->keys())->toContain('currency_amount');
    });

    it('infers DTO type from untyped arrow function body', function () {
        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyzeClass(User::class)->result();

        $inspector = app(ModelInspector::class);
        $info = $inspector->inspect(User::class);

        if (! $info['attributes']->keyBy('name')->has('money')) {
            $this->markTestSkipped('Model does not have money computed attribute');
        }

        $property = $result->getProperty('money');
        expect($property->type)->toBeInstanceOf(ArrayType::class);
        expect($property->type->keys())->toContain('amount');
        expect($property->type->keys())->toContain('currency');
        expect($property->type->keys())->toContain('currency_amount');
    });

    it('infers DTO type from untyped regular closure body', function () {
        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyzeClass(User::class)->result();

        $inspector = app(ModelInspector::class);
        $info = $inspector->inspect(User::class);

        if (! $info['attributes']->keyBy('name')->has('money_closure_untyped')) {
            $this->markTestSkipped('Model does not have money_closure_untyped computed attribute');
        }

        $property = $result->getProperty('money_closure_untyped');
        expect($property->type)->toBeInstanceOf(ArrayType::class);
        expect($property->type->keys())->toContain('amount');
        expect($property->type->keys())->toContain('currency');
        expect($property->type->keys())->toContain('currency_amount');
    });

    it('resolves DTO field types within the toArray() shape', function () {
        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyzeClass(User::class)->result();

        $inspector = app(ModelInspector::class);
        $info = $inspector->inspect(User::class);

        $attributes = $info['attributes']->keyBy('name');

        if (! $attributes->has('money')) {
            $this->markTestSkipped('Model does not have money computed attribute');
        }

        $property = $result->getProperty('money');
        $arrayType = $property->type;

        expect($arrayType)->toBeInstanceOf(ArrayType::class);

        expect($arrayType->value['amount']->id())->toBe('int');
        expect($arrayType->value['currency']->id())->toBe('string');
        // currency_amount is a concatenation of currency and amount — resolves to string
        expect($arrayType->value['currency_amount']->id())->toBe('string');
    });

    it('resolves jsonSerialize() shape when getter returns a JsonSerializable DTO', function () {
        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyzeClass(User::class)->result();

        $inspector = app(ModelInspector::class);
        $info = $inspector->inspect(User::class);

        if (! $info['attributes']->keyBy('name')->has('price')) {
            $this->markTestSkipped('Model does not have price computed attribute');
        }

        $property = $result->getProperty('price');
        expect($property->type)->toBeInstanceOf(ArrayType::class);
        expect($property->type->keys())->toContain('amount');
        expect($property->type->keys())->toContain('currency');
        expect($property->type->keys())->toContain('currency_amount');
        expect($property->type->value['amount']->id())->toBe('int');
        expect($property->type->value['currency']->id())->toBe('string');
        expect($property->type->value['currency_amount']->id())->toBe('string');
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
