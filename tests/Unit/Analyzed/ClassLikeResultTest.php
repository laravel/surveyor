<?php

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use Laravel\Surveyor\Analysis\EntityType;
use Laravel\Surveyor\Analyzed\ClassLikeResult;
use Laravel\Surveyor\Analyzed\MethodResult;
use Laravel\Surveyor\Analyzed\PropertyResult;
use Laravel\Surveyor\Types\TemplateTagType;
use Laravel\Surveyor\Types\Type;

uses()->group('results');

function createClassLikeResult(array $overrides = []): ClassLikeResult
{
    $defaults = [
        'name' => 'TestClass',
        'namespace' => 'App\\Test',
        'extends' => [],
        'implements' => [],
        'uses' => [],
        'filePath' => '/path/to/TestClass.php',
        'entityType' => EntityType::CLASS_TYPE,
    ];

    $params = array_merge($defaults, $overrides);

    return new ClassLikeResult(
        name: $params['name'],
        namespace: $params['namespace'],
        extends: $params['extends'],
        implements: $params['implements'],
        uses: $params['uses'],
        filePath: $params['filePath'],
        entityType: $params['entityType'],
    );
}

describe('ClassLikeResult basic properties', function () {
    it('stores and retrieves name', function () {
        $result = createClassLikeResult(['name' => 'UserController']);
        expect($result->name())->toBe('UserController');
    });

    it('stores and retrieves namespace', function () {
        $result = createClassLikeResult(['namespace' => 'App\\Http\\Controllers']);
        expect($result->namespace())->toBe('App\\Http\\Controllers');
    });

    it('stores and retrieves file path', function () {
        $result = createClassLikeResult(['filePath' => '/app/Controllers/Test.php']);
        expect($result->filePath())->toBe('/app/Controllers/Test.php');
    });
});

describe('extends and implements', function () {
    it('returns empty array when no extends', function () {
        $result = createClassLikeResult(['extends' => []]);
        expect($result->extends())->toBe([]);
    });

    it('returns parent classes from extends', function () {
        $result = createClassLikeResult(['extends' => ['BaseController', 'Controller']]);
        expect($result->extends())->toBe(['BaseController', 'Controller']);
    });

    it('checks if class extends a specific parent', function () {
        $result = createClassLikeResult(['extends' => ['BaseController', 'Controller']]);
        expect($result->extends('BaseController'))->toBeTrue();
        expect($result->extends('Controller'))->toBeTrue();
        expect($result->extends('NotAParent'))->toBeFalse();
    });

    it('checks multiple parents at once', function () {
        $result = createClassLikeResult(['extends' => ['BaseController', 'Controller']]);
        expect($result->extends('BaseController', 'Controller'))->toBeTrue();
        expect($result->extends('NotAParent', 'BaseController'))->toBeTrue();
        expect($result->extends('NotAParent', 'AlsoNotAParent'))->toBeFalse();
    });

    it('returns implements array', function () {
        $result = createClassLikeResult(['implements' => ['SomeInterface']]);
        expect($result->implements())->toBe(['SomeInterface']);
    });

    it('checks if class implements a specific interface', function () {
        $result = createClassLikeResult(['implements' => [JsonSerializable::class]]);
        expect($result->implements(JsonSerializable::class))->toBeTrue();
        expect($result->implements('NonExistent'))->toBeFalse();
    });

    it('checks multiple interfaces at once', function () {
        $result = createClassLikeResult(['implements' => [JsonSerializable::class, Arrayable::class]]);
        expect($result->implements(JsonSerializable::class, Arrayable::class))->toBeTrue();
        expect($result->implements('NonExistent', JsonSerializable::class))->toBeTrue();
    });
});

describe('methods', function () {
    it('adds and retrieves methods', function () {
        $result = createClassLikeResult();
        $method = new MethodResult('testMethod');

        $result->addMethod($method);

        expect($result->hasMethod('testMethod'))->toBeTrue();
        expect($result->getMethod('testMethod'))->toBe($method);
    });

    it('returns false for non-existent method', function () {
        $result = createClassLikeResult();
        expect($result->hasMethod('nonExistent'))->toBeFalse();
    });

    it('returns all public methods', function () {
        $result = createClassLikeResult();
        $method1 = new MethodResult('method1');
        $method2 = new MethodResult('method2');

        $result->addMethod($method1);
        $result->addMethod($method2);

        $methods = $result->publicMethods();
        expect($methods)->toHaveCount(2);
        expect(array_keys($methods))->toBe(['method1', 'method2']);
    });
});

describe('properties', function () {
    it('adds and retrieves properties', function () {
        $result = createClassLikeResult();
        $property = new PropertyResult('testProperty', Type::string());

        $result->addProperty($property);

        expect($result->hasProperty('testProperty'))->toBeTrue();
        expect($result->getProperty('testProperty'))->toBe($property);
    });

    it('returns false for non-existent property', function () {
        $result = createClassLikeResult();
        expect($result->hasProperty('nonExistent'))->toBeFalse();
    });

    it('returns only public properties', function () {
        $result = createClassLikeResult();
        $publicProp = new PropertyResult('publicProp', Type::string(), 'public');
        $protectedProp = new PropertyResult('protectedProp', Type::string(), 'protected');
        $privateProp = new PropertyResult('privateProp', Type::string(), 'private');

        $result->addProperty($publicProp);
        $result->addProperty($protectedProp);
        $result->addProperty($privateProp);

        $publicProps = $result->publicProperties();
        expect($publicProps)->toHaveCount(1);
        expect($publicProps[0]->name)->toBe('publicProp');
    });
});

describe('uses', function () {
    it('checks if use statement exists', function () {
        $result = createClassLikeResult(['uses' => ['Request' => 'Illuminate\\Http\\Request']]);

        expect($result->hasUse('Request'))->toBeTrue();
        expect($result->hasUse('NonExistent'))->toBeFalse();
    });

    it('returns use statement value', function () {
        $result = createClassLikeResult(['uses' => ['Request' => 'Illuminate\\Http\\Request']]);

        expect($result->getUse('Request'))->toBe('Illuminate\\Http\\Request');
        expect($result->getUse('NonExistent'))->toBeNull();
    });
});

describe('template tags', function () {
    it('starts with no template tags', function () {
        $result = createClassLikeResult();
        expect($result->templateTags())->toBe([]);
        expect($result->hasTemplateTag('T'))->toBeFalse();
        expect($result->getTemplateTag('T'))->toBeNull();
    });

    it('stores and retrieves a template tag by name', function () {
        $result = createClassLikeResult();
        $tag = new TemplateTagType(name: 'TValue', bound: null, default: null, lowerBound: null, description: null);

        $result->addTemplateTag($tag);

        expect($result->hasTemplateTag('TValue'))->toBeTrue();
        expect($result->getTemplateTag('TValue'))->toBe($tag);
    });

    it('returns all template tags keyed by name', function () {
        $result = createClassLikeResult();
        $tKey = new TemplateTagType(name: 'TKey', bound: Type::string(), default: null, lowerBound: null, description: null);
        $tValue = new TemplateTagType(name: 'TValue', bound: null, default: null, lowerBound: null, description: null);

        $result->addTemplateTag($tKey);
        $result->addTemplateTag($tValue);

        $tags = $result->templateTags();
        expect($tags)->toHaveCount(2);
        expect(array_keys($tags))->toBe(['TKey', 'TValue']);
        expect($tags['TKey'])->toBe($tKey);
        expect($tags['TValue'])->toBe($tValue);
    });

    it('overwrites an existing tag when added with the same name', function () {
        $result = createClassLikeResult();
        $first = new TemplateTagType(name: 'T', bound: null, default: null, lowerBound: null, description: null);
        $second = new TemplateTagType(name: 'T', bound: Type::int(), default: null, lowerBound: null, description: null);

        $result->addTemplateTag($first);
        $result->addTemplateTag($second);

        expect($result->templateTags())->toHaveCount(1);
        expect($result->getTemplateTag('T'))->toBe($second);
    });
});

describe('serialization helpers', function () {
    it('detects JsonSerializable implementation', function () {
        $jsonSerializable = createClassLikeResult(['implements' => [JsonSerializable::class]]);
        $notJsonSerializable = createClassLikeResult(['implements' => []]);

        expect($jsonSerializable->isJsonSerializable())->toBeTrue();
        expect($notJsonSerializable->isJsonSerializable())->toBeFalse();
    });

    it('detects Arrayable implementation', function () {
        $arrayable = createClassLikeResult(['implements' => [Arrayable::class]]);
        $notArrayable = createClassLikeResult(['implements' => []]);

        expect($arrayable->isArrayable())->toBeTrue();
        expect($notArrayable->isArrayable())->toBeFalse();
    });

    it('returns jsonSerialize method when JsonSerializable', function () {
        $result = createClassLikeResult(['implements' => [JsonSerializable::class]]);
        $method = new MethodResult('jsonSerialize');
        $result->addMethod($method);

        expect($result->asJson())->toBe($method);
    });

    it('returns null for asJson when not JsonSerializable', function () {
        $result = createClassLikeResult(['implements' => []]);

        expect($result->asJson())->toBeNull();
    });

    it('returns toArray method when Arrayable', function () {
        $result = createClassLikeResult(['implements' => [Arrayable::class]]);
        $method = new MethodResult('toArray');
        $result->addMethod($method);

        expect($result->asArray())->toBe($method);
    });

    it('returns null for asArray when not Arrayable', function () {
        $result = createClassLikeResult(['implements' => []]);

        expect($result->asArray())->toBeNull();
    });
});
