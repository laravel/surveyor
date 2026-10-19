<?php

use App\Http\Resources\BadgeCollection;
use App\Http\Resources\CatalogueCollection;
use App\Http\Resources\ChildApiResource;
use App\Http\Resources\ConditionalDefaultsResource;
use App\Http\Resources\ConditionalLabelResource;
use App\Http\Resources\CustomWrapResource;
use App\Http\Resources\FallbackMergeResource;
use App\Http\Resources\InheritedTagResource;
use App\Http\Resources\JsonApi\PostApiResource;
use App\Http\Resources\KeyedLabelResource;
use App\Http\Resources\KeyedPropertyLabelResource;
use App\Http\Resources\LoadedValueResource;
use App\Http\Resources\MergedTagResource;
use App\Http\Resources\NestsJsonApiResource;
use App\Http\Resources\PlainLabelResource;
use App\Http\Resources\PostResource;
use App\Http\Resources\TagCollection;
use App\Http\Resources\TagSummaryCollection;
use App\Http\Resources\UnknownMergeResource;
use App\Http\Resources\UnwrappedResource;
use App\Http\Resources\UserCollection;
use App\Http\Resources\UserResource;
use App\Http\Resources\WhenLookupResource;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Attributes\Collects;
use Illuminate\Http\Resources\Attributes\PreserveKeys;
use Illuminate\Http\Resources\Json\JsonResource;
use Laravel\Surveyor\Analyzer\AnalyzedCache;
use Laravel\Surveyor\Analyzer\Analyzer;
use Laravel\Surveyor\Analyzer\ResourceAnalyzer;
use Laravel\Surveyor\Types\ArrayType;
use Laravel\Surveyor\Types\ClassType;
use Laravel\Surveyor\Types\Entities\ResourceResponse;
use Laravel\Surveyor\Types\IntType;
use Laravel\Surveyor\Types\NullType;
use Laravel\Surveyor\Types\StringType;
use Laravel\Surveyor\Types\UnionType;

uses()->group('integration');

beforeEach(function () {
    AnalyzedCache::clear();
});

afterEach(function () {
    AnalyzedCache::clear();
});

describe('ResourceAnalyzer', function () {
    it('preserves fallback fields after conditional merges', function () {
        $data = app(ResourceAnalyzer::class)->buildResourceResponse(FallbackMergeResource::class)->data;

        expect($data->value['before']->value)->toBe('first')
            ->and($data->value['after'])->toBeInstanceOf(UnionType::class)
            ->and($data->value['after']->isOptional())->toBeFalse()
            ->and($data->value['after']->types[0])->toBeInstanceOf(IntType::class)
            ->and($data->value['after']->types[1])->toBeInstanceOf(StringType::class)
            ->and($data->value['optional'])->toBeInstanceOf(UnionType::class)
            ->and($data->value['optional']->isOptional())->toBeTrue();

        foreach ([false, true] as $details) {
            $actual = (new FallbackMergeResource([]))->resolve(Request::create('/', 'GET', ['details' => $details]));

            expect($actual['before'])->toBe('first')
                ->and($actual['after'])->toBe($details ? 1 : 'fallback')
                ->and(array_key_exists('optional', $actual))->toBe($details);
        }
    });

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
        expect($resourceResponse->wrap())->toBe('data');
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
        expect($resourceResponse->wrap())->toBeNull();
    });

    it('handles custom wrap property', function () {
        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyzeClass(CustomWrapResource::class)->result();

        $resourceResponse = app(ResourceAnalyzer::class)->buildResourceResponse($result->name());
        expect($resourceResponse)->not->toBeNull();
        expect($resourceResponse->wrap())->toBe('results');
    });

    it('detects ResourceCollection as collection', function () {
        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyzeClass(UserCollection::class)->result();

        $resourceResponse = app(ResourceAnalyzer::class)->buildResourceResponse($result->name());
        expect($resourceResponse)->not->toBeNull();
        expect($resourceResponse->isCollection)->toBeFalse();
        expect($resourceResponse->data->value['data']->genericTypes()[1])->toBeInstanceOf(ResourceResponse::class);
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

    it('preserves explicit conditional defaults and missing values', function () {
        $data = app(ResourceAnalyzer::class)->buildResourceResponse(ConditionalDefaultsResource::class)->data;

        foreach (['when', 'nullable_default', 'named_default', 'has', 'count', 'aggregate', 'pivot', 'pivot_as'] as $key) {
            expect($data->value[$key]->isOptional())->toBeFalse($key);
        }

        foreach (['unless', 'missing_default', 'appended', 'not_null', 'exists', 'nested', 'nested_new', 'nested_collection'] as $key) {
            expect($data->value[$key]->isOptional())->toBeTrue($key);
        }

        expect($data->value['when'])->toBeInstanceOf(UnionType::class)
            ->and($data->value['nullable_default']->isNullable())->toBeTrue()
            ->and($data->value['not_null']->isNullable())->toBeFalse()
            ->and($data->value['label']->isOptional())->toBeFalse()
            ->and($data->value['appended'])->toBeInstanceOf(StringType::class);
    });

    it('flattens resource merges and preserves common default keys', function () {
        $data = app(ResourceAnalyzer::class)->buildResourceResponse(ConditionalDefaultsResource::class)->data;

        foreach (['always', 'common'] as $key) {
            expect($data->value[$key]->isOptional())->toBeFalse($key);
        }

        foreach (['secret', 'unless_merged', 'primary', 'fallback'] as $key) {
            expect($data->value[$key]->isOptional())->toBeTrue($key);
        }

        expect($data->value['common'])->toBeInstanceOf(UnionType::class)
            ->and($data->value['ordinary_array'])->toBeInstanceOf(ArrayType::class)
            ->and($data->value)->not->toHaveKey(0);
    });

    it('resolves whenNull independently of the input type and includes explicit defaults', function () {
        $data = app(ResourceAnalyzer::class)->buildResourceResponse(ConditionalDefaultsResource::class)->data;

        expect($data->value['null_value'])->toBeInstanceOf(NullType::class)
            ->and($data->value['null_value']->isOptional())->toBeTrue()
            ->and($data->value['null_with_default'])->toBeInstanceOf(StringType::class)
            ->and($data->value['null_with_default']->isNullable())->toBeTrue()
            ->and($data->value['null_with_default']->isOptional())->toBeFalse();
    });

    it('maps named aggregate arguments regardless of their order', function () {
        $data = app(ResourceAnalyzer::class)->buildResourceResponse(ConditionalDefaultsResource::class)->data;

        expect($data->value['named_aggregate'])->toBeInstanceOf(UnionType::class)
            ->and($data->value['named_aggregate']->isOptional())->toBeFalse()
            ->and($data->value['named_aggregate']->types[0])->toBeInstanceOf(IntType::class)
            ->and($data->value['named_aggregate']->types[1])->toBeInstanceOf(StringType::class);
    });

    it('infers merge shapes from array-returning closures in both branches', function () {
        $data = app(ResourceAnalyzer::class)->buildResourceResponse(ConditionalDefaultsResource::class)->data;

        expect($data->value['closure_common'])->toBeInstanceOf(UnionType::class)
            ->and($data->value['closure_common']->isOptional())->toBeFalse()
            ->and($data->value['closure_primary']->isOptional())->toBeTrue()
            ->and($data->value['closure_fallback']->isOptional())->toBeTrue();
    });

    it('preserves optional merge members and handles defaults without array keys', function () {
        $data = app(ResourceAnalyzer::class)->buildResourceResponse(ConditionalDefaultsResource::class)->data;

        foreach (['still_optional', 'missing_merge', 'null_merge'] as $key) {
            expect($data->value[$key]->isOptional())->toBeTrue($key);
        }
    });

    it('distinguishes inherited collections from custom collection shapes', function () {
        $analyzer = app(ResourceAnalyzer::class);
        $collection = $analyzer->buildResourceResponse(TagCollection::class);
        $custom = $analyzer->buildResourceResponse(TagSummaryCollection::class);

        expect($collection->isCollection)->toBeTrue()
            ->and($collection->data->keys())->toBe(['label'])
            ->and($custom->isCollection)->toBeFalse()
            ->and($custom->data->keys())->toBe(['items', 'count', 'summary'])
            ->and($custom->data->value['items'])->toBeInstanceOf(ClassType::class)
            ->and($custom->data->value['items']->genericTypes()[1])->toBeInstanceOf(ResourceResponse::class)
            ->and($custom->data->value['count'])->toBeInstanceOf(IntType::class);
    });

    it('uses anonymous collection wrapping without item metadata', function () {
        $analyzer = app(ResourceAnalyzer::class);

        expect($analyzer->buildResourceResponse(CustomWrapResource::class, true)->wrap())->toBe('data')
            ->and($analyzer->buildResourceResponse(UserResource::class, true)->additional)->toBeNull();

        JsonResource::withoutWrapping();

        try {
            expect($analyzer->buildResourceResponse(UserResource::class)->wrap())->toBeNull()
                ->and($analyzer->buildResourceResponse(CustomWrapResource::class, true)->wrap())->toBeNull();
        } finally {
            JsonResource::wrap('data');
        }
    });

    it('uses an inherited resource transformation with subclass wrapping', function () {
        $response = app(ResourceAnalyzer::class)->buildResourceResponse(InheritedTagResource::class);

        expect($response)->toBeInstanceOf(ResourceResponse::class)
            ->and($response->wrap())->toBe('tag')
            ->and($response->data->value['label'])->toBeInstanceOf(StringType::class);
    });

    it('matches Laravel merge precedence for string keys', function () {
        $response = app(ResourceAnalyzer::class)->buildResourceResponse(MergedTagResource::class);
        $actual = (new MergedTagResource([]))->resolve();

        expect($actual)->toBe(['before' => 'first', 'after' => 'merged'])
            ->and($response->data->value['before']->value)->toBe($actual['before'])
            ->and($response->data->value['after']->value)->toBe($actual['after']);
    });
});

describe('resource response configuration', function () {
    it('reads wrapping when it is asked for rather than when it was analyzed', function () {
        $cached = unserialize(serialize(app(ResourceAnalyzer::class)->buildResourceResponse(UserResource::class)));

        JsonResource::withoutWrapping();

        try {
            expect($cached->wrap())->toBeNull();
        } finally {
            JsonResource::wrap('data');
        }

        expect($cached->wrap())->toBe('data');
    });

    it('reads forced wrapping from the resource that responds', function () {
        $response = app(ResourceAnalyzer::class)->buildResourceResponse(UserResource::class);

        expect($response->forcesWrapping())->toBeFalse();

        JsonResource::$forceWrapping = true;

        try {
            expect($response->forcesWrapping())->toBeTrue();
        } finally {
            JsonResource::$forceWrapping = false;
        }
    })->skip(! property_exists(JsonResource::class, 'forceWrapping'), 'Forced wrapping is not available in this Laravel version.');

    it('preserves collection keys configured by the collected resource', function () {
        $analyzer = app(ResourceAnalyzer::class);

        expect($analyzer->buildResourceResponse(KeyedPropertyLabelResource::class, true)->preservesKeys())->toBeTrue()
            ->and($analyzer->buildResourceResponse(KeyedPropertyLabelResource::class)->preservesKeys())->toBeFalse()
            ->and($analyzer->buildResourceResponse(PlainLabelResource::class, true)->preservesKeys())->toBeFalse();
    });

    it('preserves collection keys for a resource marked with the attribute', function () {
        expect(app(ResourceAnalyzer::class)->buildResourceResponse(KeyedLabelResource::class, true)->preservesKeys())->toBeTrue();
    })->skip(! class_exists(PreserveKeys::class), 'Resource attributes are not available in this Laravel version.');

    it('finds a collected resource named after the collection', function () {
        $badges = app(ResourceAnalyzer::class)->buildResourceResponse(BadgeCollection::class);

        expect($badges->isCollection)->toBeTrue()
            ->and($badges->data->keys())->toBe(['badge']);
    });

    it('finds a collected resource named by the Collects attribute', function () {
        $catalogue = app(ResourceAnalyzer::class)->buildResourceResponse(CatalogueCollection::class);

        expect($catalogue->isCollection)->toBeTrue()
            ->and($catalogue->data->keys())->toBe(['label']);
    })->skip(! class_exists(Collects::class), 'Resource attributes are not available in this Laravel version.');

    it('does not mark the cached JSON:API response nullable for one nested use', function () {
        $analyzer = app(ResourceAnalyzer::class);

        expect($analyzer->buildResourceResponse(NestsJsonApiResource::class)->data->value['post']->isNullable())->toBeTrue()
            ->and($analyzer->buildResourceResponse(PostApiResource::class)->isNullable())->toBeFalse();
    });
});

describe('conditional values', function () {
    it('makes a loaded value nullable when the relation it replaces can be null', function () {
        $data = app(ResourceAnalyzer::class)->buildResourceResponse(LoadedValueResource::class)->data->value;

        expect($data['loaded'])->toBeInstanceOf(StringType::class)
            ->and($data['loaded']->value)->toBe('loaded')
            ->and($data['loaded']->isNullable())->toBeTrue()
            ->and($data['loaded']->isOptional())->toBeTrue()
            ->and($data['counted'])->toBeInstanceOf(StringType::class)
            ->and($data['counted']->isNullable())->toBeFalse()
            ->and($data['aggregated'])->toBeInstanceOf(IntType::class)
            ->and($data['aggregated']->isNullable())->toBeTrue()
            ->and($data['exists']->value)->toBe('yes')
            ->and($data['exists']->isNullable())->toBeFalse();
    });

    it('treats an explicit null value as the loaded value itself', function () {
        $data = app(ResourceAnalyzer::class)->buildResourceResponse(LoadedValueResource::class)->data->value;

        // The label itself, which can be null, or the default.
        expect($data['loaded_itself'])->toBeInstanceOf(UnionType::class)
            ->and($data['loaded_itself']->isOptional())->toBeFalse()
            ->and($data['loaded_itself']->types[0])->toBeInstanceOf(StringType::class)
            ->and($data['loaded_itself']->types[0]->isNullable())->toBeTrue()
            ->and($data['loaded_itself']->types[1])->toBeInstanceOf(IntType::class);
    });

    it('adds nothing for a merge whose keys cannot be read', function () {
        $data = app(ResourceAnalyzer::class)->buildResourceResponse(UnknownMergeResource::class)->data;

        expect($data->keys())->toBe(['id', 'after']);
    });
});
