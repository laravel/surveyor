<?php

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use Laravel\Surveyor\Analyzed\ClassResult;
use Laravel\Surveyor\Analyzed\MethodResult;
use Laravel\Surveyor\Analyzed\PropertyResult;
use Laravel\Surveyor\Types\Type;

uses()->group('results');

function createClassResult(array $overrides = []): ClassResult
{
    $defaults = [
        'name' => 'TestClass',
        'namespace' => 'App\\Test',
        'extends' => [],
        'implements' => [],
        'uses' => [],
        'filePath' => '/path/to/TestClass.php',
    ];

    $params = array_merge($defaults, $overrides);

    return new ClassResult(
        name: $params['name'],
        namespace: $params['namespace'],
        extends: $params['extends'],
        implements: $params['implements'],
        uses: $params['uses'],
        filePath: $params['filePath'],
    );
}

describe('ClassResult basic properties', function () {
    it('stores and retrieves name', function () {
        $result = createClassResult(['name' => 'UserController']);
        expect($result->name())->toBe('UserController');
    });

    it('stores and retrieves namespace', function () {
        $result = createClassResult(['namespace' => 'App\\Http\\Controllers']);
        expect($result->namespace())->toBe('App\\Http\\Controllers');
    });

    it('stores and retrieves file path', function () {
        $result = createClassResult(['filePath' => '/app/Controllers/Test.php']);
        expect($result->filePath())->toBe('/app/Controllers/Test.php');
    });
});

describe('extends and implements', function () {
    it('returns empty array when no extends', function () {
        $result = createClassResult(['extends' => []]);
        expect($result->extends())->toBe([]);
    });

    it('returns parent classes from extends', function () {
        $result = createClassResult(['extends' => ['BaseController', 'Controller']]);
        expect($result->extends())->toBe(['BaseController', 'Controller']);
    });

    it('checks if class extends a specific parent', function () {
        $result = createClassResult(['extends' => [['BaseController']]]);
        expect($result->extends('BaseController'))->toBeTrue();
    });

    it('returns implements array', function () {
        $result = createClassResult(['implements' => ['SomeInterface']]);
        expect($result->implements())->toBe(['SomeInterface']);
    });

    it('checks if class implements a specific interface', function () {
        $result = createClassResult(['implements' => [JsonSerializable::class]]);
        expect($result->implements(JsonSerializable::class))->toBeTrue();
        expect($result->implements('NonExistent'))->toBeFalse();
    });

    it('checks multiple interfaces at once', function () {
        $result = createClassResult(['implements' => [JsonSerializable::class, Arrayable::class]]);
        expect($result->implements(JsonSerializable::class, Arrayable::class))->toBeTrue();
        expect($result->implements('NonExistent', JsonSerializable::class))->toBeTrue();
    });
});

describe('methods', function () {
    it('adds and retrieves methods', function () {
        $result = createClassResult();
        $method = new MethodResult('testMethod');

        $result->addMethod($method);

        expect($result->hasMethod('testMethod'))->toBeTrue();
        expect($result->getMethod('testMethod'))->toBe($method);
    });

    it('returns false for non-existent method', function () {
        $result = createClassResult();
        expect($result->hasMethod('nonExistent'))->toBeFalse();
    });

    it('returns all public methods', function () {
        $result = createClassResult();
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
        $result = createClassResult();
        $property = new PropertyResult('testProperty', Type::string());

        $result->addProperty($property);

        expect($result->hasProperty('testProperty'))->toBeTrue();
        expect($result->getProperty('testProperty'))->toBe($property);
    });

    it('returns false for non-existent property', function () {
        $result = createClassResult();
        expect($result->hasProperty('nonExistent'))->toBeFalse();
    });

    it('returns only public properties', function () {
        $result = createClassResult();
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
        $result = createClassResult(['uses' => ['Request' => 'Illuminate\\Http\\Request']]);

        expect($result->hasUse('Request'))->toBeTrue();
        expect($result->hasUse('NonExistent'))->toBeFalse();
    });

    it('returns use statement value', function () {
        $result = createClassResult(['uses' => ['Request' => 'Illuminate\\Http\\Request']]);

        expect($result->getUse('Request'))->toBe('Illuminate\\Http\\Request');
        expect($result->getUse('NonExistent'))->toBeNull();
    });
});

describe('serialization helpers', function () {
    it('detects JsonSerializable implementation', function () {
        $jsonSerializable = createClassResult(['implements' => [JsonSerializable::class]]);
        $notJsonSerializable = createClassResult(['implements' => []]);

        expect($jsonSerializable->isJsonSerializable())->toBeTrue();
        expect($notJsonSerializable->isJsonSerializable())->toBeFalse();
    });

    it('detects Arrayable implementation', function () {
        $arrayable = createClassResult(['implements' => [Arrayable::class]]);
        $notArrayable = createClassResult(['implements' => []]);

        expect($arrayable->isArrayable())->toBeTrue();
        expect($notArrayable->isArrayable())->toBeFalse();
    });

    it('returns jsonSerialize method when JsonSerializable', function () {
        $result = createClassResult(['implements' => [JsonSerializable::class]]);
        $method = new MethodResult('jsonSerialize');
        $result->addMethod($method);

        expect($result->asJson())->toBe($method);
    });

    it('returns null for asJson when not JsonSerializable', function () {
        $result = createClassResult(['implements' => []]);

        expect($result->asJson())->toBeNull();
    });

    it('returns toArray method when Arrayable', function () {
        $result = createClassResult(['implements' => [Arrayable::class]]);
        $method = new MethodResult('toArray');
        $result->addMethod($method);

        expect($result->asArray())->toBe($method);
    });

    it('returns null for asArray when not Arrayable', function () {
        $result = createClassResult(['implements' => []]);

        expect($result->asArray())->toBeNull();
    });
});
