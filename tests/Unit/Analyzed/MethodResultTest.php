<?php

use Laravel\Surveyor\Analyzed\MethodResult;
use Laravel\Surveyor\Types\IntType;
use Laravel\Surveyor\Types\MixedType;
use Laravel\Surveyor\Types\StringType;
use Laravel\Surveyor\Types\Type;
use Laravel\Surveyor\Types\UnionType;

uses()->group('results');

describe('MethodResult basic properties', function () {
    it('stores and retrieves name', function () {
        $method = new MethodResult('testMethod');
        expect($method->name())->toBe('testMethod');
    });
});

describe('parameters', function () {
    it('starts with no parameters', function () {
        $method = new MethodResult('test');
        expect($method->parameters())->toBe([]);
    });

    it('adds and retrieves parameters', function () {
        $method = new MethodResult('test');
        $method->addParameter('name', Type::string());
        $method->addParameter('age', Type::int());

        $params = $method->parameters();
        expect($params)->toHaveCount(2);
        expect($params)->toHaveKey('name');
        expect($params)->toHaveKey('age');
        expect($params['name'])->toBeInstanceOf(StringType::class);
        expect($params['age'])->toBeInstanceOf(IntType::class);
    });
});

describe('return types', function () {
    it('starts with mixed return type', function () {
        $method = new MethodResult('test');
        $returnType = $method->returnType();
        expect($returnType)->toBeInstanceOf(MixedType::class);
    });

    it('adds and retrieves single return type', function () {
        $method = new MethodResult('test');
        $method->addReturnType(Type::string(), 10);

        $returnType = $method->returnType();
        expect($returnType)->toBeInstanceOf(StringType::class);
    });

    it('creates union for multiple return types', function () {
        $method = new MethodResult('test');
        $method->addReturnType(Type::string(), 10);
        $method->addReturnType(Type::int(), 15);

        $returnType = $method->returnType();
        expect($returnType)->toBeInstanceOf(UnionType::class);
        expect($returnType->types)->toHaveCount(2);
    });

    it('stores line numbers with return types', function () {
        $method = new MethodResult('test');
        $method->addReturnType(Type::string(), 42);

        $returnType = $method->returnType();
        expect($returnType)->toBeInstanceOf(StringType::class);
    });
});

describe('validation rules', function () {
    it('starts with no validation rules', function () {
        $method = new MethodResult('test');
        expect($method->validationRules())->toBe([]);
    });

    it('adds and retrieves validation rules', function () {
        $method = new MethodResult('rules');
        $method->addValidationRule('email', [['email'], ['required']]);
        $method->addValidationRule('name', [['string'], ['max' => 255]]);

        $rules = $method->validationRules();
        expect($rules)->toHaveCount(2);
        expect($rules)->toHaveKey('email');
        expect($rules)->toHaveKey('name');
    });
});

describe('model relation flag', function () {
    it('starts as not a model relation', function () {
        $method = new MethodResult('test');
        expect($method->isModelRelation())->toBeFalse();
    });

    it('can be flagged as model relation', function () {
        $method = new MethodResult('posts');
        $method->flagAsModelRelation();

        expect($method->isModelRelation())->toBeTrue();
    });
});
