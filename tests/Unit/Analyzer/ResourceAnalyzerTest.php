<?php

use App\Http\Resources\ChildApiResource;
use App\Http\Resources\ConditionalLabelResource;
use App\Http\Resources\ConditionalShapeResource;
use App\Http\Resources\CustomAttributesCollection;
use App\Http\Resources\CustomPayloadCollection;
use App\Http\Resources\CustomResolveResource;
use App\Http\Resources\CustomWrapResource;
use App\Http\Resources\PlainLabelResource;
use App\Http\Resources\PostResource;
use App\Http\Resources\UnwrappedResource;
use App\Http\Resources\UserCollection;
use App\Http\Resources\UserResource;
use App\Http\Resources\WhenLookupResource;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Laravel\Surveyor\Analyzer\AnalyzedCache;
use Laravel\Surveyor\Analyzer\Analyzer;
use Laravel\Surveyor\Analyzer\ResourceAnalyzer;
use Laravel\Surveyor\Types\ArrayType;
use Laravel\Surveyor\Types\Contracts\Type as TypeContract;
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

    it('respects custom resource and collection resolution', function (string $expression, TypeContract $expected, Closure $resolve, array $expectedData) {
        $fixture = createPhpFixture('
namespace App\\Test;

use App\\Http\\Resources\\CustomAttributesCollection;
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
            expect($resolve())->toBe($expectedData);

            $result = app(Analyzer::class)->analyze($fixture)->result();

            expect($result->getMethod('index')->returnType())->toEqual($expected);
        } finally {
            unlink($fixture);
        }
    })->with([
        'overridden resolve' => [
            '(new CustomResolveResource(null))->resolve()',
            fn () => Type::array(['custom' => Type::int()]),
            fn () => (new CustomResolveResource(null))->resolve(),
            ['custom' => 42],
        ],
        'custom collection payload' => [
            '(new CustomPayloadCollection([null]))->resolve()',
            fn () => Type::array(['count' => Type::int(42)]),
            fn () => (new CustomPayloadCollection([null]))->resolve(Request::create('/')),
            ['count' => 42],
        ],
        'custom collection attributes' => [
            '(new CustomAttributesCollection([null]))->resolve()',
            fn () => Type::array(['attributes' => Type::int(42)]),
            fn () => (new CustomAttributesCollection([null]))->resolve(Request::create('/')),
            ['attributes' => 42],
        ],
        'collected resource override' => [
            'CustomResolveResource::collection([null])->resolve()',
            fn () => Type::arrayShape(Type::union(Type::int(), Type::string()), Type::array(['custom' => Type::int(42)])),
            fn () => CustomResolveResource::collection([null])->resolve(Request::create('/')),
            [['custom' => 42]],
        ],
    ]);

    it('uses the effective resolution method without guessing inherited or property-backed data', function (string $parent, string $override, bool $analyzed) {
        $class = 'ResolutionResource'.md5($parent.$override);
        $fixture = createPhpFixture('
namespace App\\Test;

use Illuminate\\Http\\Request;

class '.$class.' extends \\'.$parent.'
{
    public function toArray(Request $request): array
    {
        return ["legacy" => 1];
    }

    '.$override.'
}');

        $resource = 'App\\Test\\'.$class;
        $caller = createPhpFixture('
class ResourceController
{
    public function index()
    {
        return \\'.$resource.'::collection([null])->resolve();
    }
}');

        try {
            require $fixture;

            expect($resource::collection([null])->resolve(Request::create('/')))->toBe([['custom' => 42]]);

            $response = app(ResourceAnalyzer::class)->buildResourceResponse($resource);

            expect($response?->data)->toEqual($analyzed ? Type::array(['custom' => Type::int(42)]) : null);

            $result = app(Analyzer::class)->analyze($caller)->result();

            expect($result->getMethod('index')->returnType())->toEqual($analyzed
                ? Type::arrayShape(Type::union(Type::int(), Type::string()), Type::array(['custom' => Type::int(42)]))
                : Type::array([]));
        } finally {
            unlink($caller);
            unlink($fixture);
        }
    })->with([
        'resolveResourceData override' => [
            JsonResource::class,
            'public function resolveResourceData(Request $request): array { return ["custom" => 42]; }',
            true,
        ],
        'toAttributes override' => [
            JsonResource::class,
            'public function toAttributes(Request $request): array { return ["custom" => 42]; }',
            true,
        ],
        'attributes property' => [
            JsonResource::class,
            'public array $attributes = ["custom" => 42];',
            false,
        ],
        'inherited resolve override' => [CustomResolveResource::class, '', false],
    ]);

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
