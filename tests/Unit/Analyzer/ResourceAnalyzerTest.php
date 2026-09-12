<?php

use App\Http\Resources\ChildApiResource;
use App\Http\Resources\ConditionalLabelResource;
use App\Http\Resources\ConditionalShapeResource;
use App\Http\Resources\CustomWrapResource;
use App\Http\Resources\PlainLabelResource;
use App\Http\Resources\PostResource;
use App\Http\Resources\UnwrappedResource;
use App\Http\Resources\UserCollection;
use App\Http\Resources\UserResource;
use App\Http\Resources\WhenLookupResource;
use App\Models\Tag;
use Illuminate\Http\Request;
use Laravel\Surveyor\Analyzer\AnalyzedCache;
use Laravel\Surveyor\Analyzer\Analyzer;
use Laravel\Surveyor\Analyzer\ResourceAnalyzer;
use Laravel\Surveyor\Types\ArrayType;
use Laravel\Surveyor\Types\Entities\ResourceResponse;
use Laravel\Surveyor\Types\StringType;
use Laravel\Surveyor\Types\Type;

uses()->group('integration');

beforeEach(function () {
    AnalyzedCache::clear();
});

afterEach(function () {
    AnalyzedCache::clear();
});

describe('ResourceAnalyzer', function () {
    it('preserves resolved resource payloads without their response wrapping', function (string $expression, bool $isCollection) {
        $fixture = createPhpFixture('
namespace App\\Test;

use Illuminate\\Http\\Request;
use App\\Http\\Resources\\ConditionalShapeResource;

class ResourceController
{
    public function index(Request $request)
    {
        return '.$expression.';
    }
}');

        try {
            $result = app(Analyzer::class)->analyze($fixture)->result();
            $data = Type::union(
                Type::array(['id' => Type::int(1), 'name' => Type::string('Ada')]),
                Type::array(['id' => Type::int(1)]),
            );

            expect($result->getMethod('index')->returnType())->toEqual($isCollection
                ? Type::arrayShape(Type::union(Type::int(), Type::string()), $data)
                : Type::array(['id' => Type::int(1), 'name' => Type::string('Ada')->optional()]));

            expect(app(ResourceAnalyzer::class)->buildResourceResponse(ConditionalShapeResource::class)->data)->toEqual($data);

            $request = Request::create('/');
            $resolved = $isCollection
                ? ConditionalShapeResource::collection(['featured' => null])->resolve($request)
                : (new ConditionalShapeResource(null))->resolve($request);

            expect($resolved)->toBe($isCollection ? ['featured' => ['id' => 1]] : ['id' => 1]);
        } finally {
            unlink($fixture);
        }
    })->with([
        'resource with null data' => ['(new ConditionalShapeResource(null))->resolve($request)', false],
        'resource collection' => ['ConditionalShapeResource::collection([])->resolve($request)', true],
    ]);

    it('respects a resource that overrides resolve instead of returning its toArray shape', function () {
        $fixture = createPhpFixture('
namespace App\\Test;

use App\\Http\\Resources\\CustomResolveResource;

class ResourceController
{
    public function index()
    {
        return (new CustomResolveResource(null))->resolve();
    }
}');

        try {
            $result = app(Analyzer::class)->analyze($fixture)->result();

            expect($result->getMethod('index')->returnType())->toEqual(Type::array(['custom' => Type::int()]));
        } finally {
            unlink($fixture);
        }
    });

    it('resolves the collection implementation rather than the collected resource override', function (bool $customPayload) {
        $expression = $customPayload
            ? '(new CustomPayloadCollection([]))->resolve()'
            : 'CustomResolveResource::collection([])->resolve()';
        $fixture = createPhpFixture('
namespace App\\Test;

use App\\Http\\Resources\\CustomPayloadCollection;
use App\\Http\\Resources\\CustomResolveResource;

class ResourceController
{
    public function index()
    {
        return '.$expression.';
    }
}');

        try {
            $result = app(Analyzer::class)->analyze($fixture)->result();

            expect($result->getMethod('index')->returnType())->toEqual($customPayload
                ? Type::array(['count' => Type::int(42)])
                : Type::arrayShape(Type::union(Type::int(), Type::string()), Type::array(['name' => Type::string('Ada')])));
        } finally {
            unlink($fixture);
        }
    })->with([true, false]);

    it('preserves every toArray return shape for resources and collections', function (bool $isCollection) {
        $response = app(ResourceAnalyzer::class)->buildResourceResponse(ConditionalShapeResource::class, $isCollection);

        expect($response)->toBeInstanceOf(ResourceResponse::class);
        expect($response->isCollection)->toBe($isCollection);
        expect($response->wrap)->toBe('data');
        expect($response->data)->toEqual(Type::union(
            Type::array(['id' => Type::int(1), 'name' => Type::string('Ada')]),
            Type::array(['id' => Type::int(1)]),
        ));
    })->with([false, true]);

    it('detects resource class and extracts toArray shape', function () {
        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyzeClass(PostResource::class)->result();

        expect($result)->not->toBeNull();

        $resourceResponse = app(ResourceAnalyzer::class)->buildResourceResponse($result->name());
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

        $resourceResponse = app(ResourceAnalyzer::class)->buildResourceResponse($result->name());
        expect($resourceResponse)->not->toBeNull();

        // The toArray keys should have resolved types from the Post model
        $data = $resourceResponse->data;
        expect($data)->toBeInstanceOf(ArrayType::class);
    });

    it('marks conditional attributes as optional', function () {
        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyzeClass(UserResource::class)->result();

        $resourceResponse = app(ResourceAnalyzer::class)->buildResourceResponse($result->name());
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

        $resourceResponse = app(ResourceAnalyzer::class)->buildResourceResponse($result->name());
        expect($resourceResponse)->not->toBeNull();
        expect($resourceResponse->additional)->toBeInstanceOf(ArrayType::class);
        expect($resourceResponse->additional->keys())->toContain('meta');
    });

    it('handles null wrap property', function () {
        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyzeClass(UnwrappedResource::class)->result();

        $resourceResponse = app(ResourceAnalyzer::class)->buildResourceResponse($result->name());
        expect($resourceResponse)->not->toBeNull();
        expect($resourceResponse->wrap)->toBeNull();
    });

    it('handles custom wrap property', function () {
        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyzeClass(CustomWrapResource::class)->result();

        $resourceResponse = app(ResourceAnalyzer::class)->buildResourceResponse($result->name());
        expect($resourceResponse)->not->toBeNull();
        expect($resourceResponse->wrap)->toBe('results');
    });

    it('detects ResourceCollection as collection', function () {
        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyzeClass(UserCollection::class)->result();

        $resourceResponse = app(ResourceAnalyzer::class)->buildResourceResponse($result->name());
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

    it('detects resource through intermediate parent class', function () {
        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyzeClass(ChildApiResource::class)->result();

        $resourceResponse = app(ResourceAnalyzer::class)->buildResourceResponse($result->name());
        expect($resourceResponse)->not->toBeNull();
        expect($resourceResponse->data)->toBeInstanceOf(ArrayType::class);
        expect($resourceResponse->data->keys())->toContain('id');
        expect($resourceResponse->data->keys())->toContain('title');
    });

    // Regression: conditional helpers (when, mergeWhen, ...) used to mutate the
    // shared model property type instance, leaking optionality across resources
    // that share a model. ConditionalLabelResource wraps Tag::$label in when(),
    // PlainLabelResource then reads $this->label directly — it must still be required.
    it('does not leak optionality across resources sharing a model', function () {
        $analyzer = app(Analyzer::class);

        $analyzer->analyzeClass(ConditionalLabelResource::class);

        $tagResult = $analyzer->analyzeClass(Tag::class)->result();
        $labelProperty = collect($tagResult->publicProperties())->firstWhere('name', 'label');
        expect($labelProperty)->not->toBeNull();
        expect($labelProperty->type->isOptional())->toBeFalse();

        $plain = $analyzer->analyzeClass(PlainLabelResource::class)->result();
        $data = app(ResourceAnalyzer::class)->buildResourceResponse($plain->name())->data;

        expect($data->value)->toHaveKey('label');
        expect($data->value['label']->isOptional())->toBeFalse();
    });

    it('resolves whenHas() and whenLoaded() to the typed model property and marks them optional', function () {
        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyzeClass(WhenLookupResource::class)->result();

        $data = app(ResourceAnalyzer::class)->buildResourceResponse($result->name())->data;

        foreach (['has_label', 'loaded_label'] as $key) {
            expect($data->value)->toHaveKey($key);
            expect($data->value[$key]->isOptional())->toBeTrue();
            expect($data->value[$key])->toBeInstanceOf(StringType::class);
        }
    });
});
