<?php

use Laravel\Surveyor\Analyzer\AnalyzedCache;
use Laravel\Surveyor\Analyzer\Analyzer;
use Laravel\Surveyor\Types\ArrayType;
use Laravel\Surveyor\Types\Type;

uses()->group('integration');

beforeEach(function () {
    AnalyzedCache::clear();

    app()->forgetInstance(Analyzer::class);
});

afterEach(function () {
    AnalyzedCache::clear();
});

describe('a method call spread or merged into an array', function () {
    it('keeps the keys returned by a method from a trait', function () {
        $returnType = analyzeFile('app/Http/Requests/StoreGroupRequest.php')
            ->result()
            ->getMethod('rules')
            ->returnType();

        expect($returnType)->toBeInstanceOf(ArrayType::class);
        expect($returnType->keys())->toContain('name', 'price_amount', 'price_currency');
    });

    it('keeps the keys returned by a method whose docblock only names the key and value types', function () {
        $returnType = analyzeFile('app/Http/Requests/StoreGroupRequest.php')
            ->result()
            ->getMethod('rules')
            ->returnType();

        expect($returnType->keys())->toContain('note');
    });

    it('keeps the keys returned by a method written further down the class', function () {
        $returnType = analyzeFile('app/Http/Requests/StoreGroupRequest.php')
            ->result()
            ->getMethod('rules')
            ->returnType();

        expect($returnType->keys())->toContain('email');
    });

    it('follows a method further down that spreads one further down still', function () {
        $returnType = analyzeFile('app/Http/Requests/StoreGroupRequest.php')
            ->result()
            ->getMethod('rules')
            ->returnType();

        expect($returnType->keys())->toContain('city');
    });

    it('collects the validation rules that were spread in', function () {
        $rules = analyzeFile('app/Http/Requests/StoreGroupRequest.php')
            ->result()
            ->getMethod('rules')
            ->validationRules();

        expect(array_keys($rules))->toBe(['name', 'price_amount', 'price_currency', 'note', 'email', 'city']);
        expect($rules['price_amount'])->toBe([
            ['Required', []],
            ['Numeric', []],
            ['Min', ['0']],
        ]);
    });

    it('keeps the keys when the array is made of nothing but spreads', function () {
        $returnType = analyzeFile('app/Http/Requests/StoreGroupRequest.php')
            ->result()
            ->getMethod('onlySpreads')
            ->returnType();

        expect($returnType)->toBeInstanceOf(ArrayType::class);
        expect($returnType->keys())->toBe(['price_amount', 'price_currency', 'note']);
    });

    it('keeps the keys of a method call handed to array_merge', function () {
        $returnType = analyzeFile('app/Http/Requests/StoreGroupRequest.php')
            ->result()
            ->getMethod('merged')
            ->returnType();

        expect($returnType)->toBeInstanceOf(ArrayType::class);
        expect($returnType->keys())->toBe(['price_amount', 'price_currency', 'name']);
    });

    it('leaves the declared type alone when the body does not return a literal', function () {
        $returnType = analyzeFile('app/Spreading/Unanswerable.php')
            ->result()
            ->getMethod('rules')
            ->returnType();

        expect($returnType)->toBeInstanceOf(ArrayType::class);
        expect($returnType->keys())->toBe(['name']);
    });

    it('settles two methods that spread each other', function () {
        $result = analyzeFile('app/Spreading/Circular.php')->result();

        expect($result->getMethod('first')->returnType()->keys())->toEqualCanonicalizing(['first', 'second']);
        expect($result->getMethod('second')->returnType()->keys())->toEqualCanonicalizing(['first', 'second']);
    });
});

describe('a method further down the class', function () {
    it('keeps a trait\'s own methods when the trait is analyzed on its own', function () {
        $result = analyzeFile('app/Http/Requests/Concerns/HasPriceRules.php')->result();

        expect($result->name())->toBe('App\\Http\\Requests\\Concerns\\HasPriceRules');
        expect($result->getMethod('priceRules')->returnType()->keys())->toBe(['price_amount', 'price_currency']);
    });

    it('does not grow the answer of a method that only asks for itself', function () {
        $returnType = analyzeFile('app/Spreading/Recursive.php')
            ->result()
            ->getMethod('ancestors')
            ->returnType();

        // The base case returns [], so the answer is that or the single ancestor.
        $collapsed = Type::collapseArrays($returnType);

        expect($collapsed)->toBeInstanceOf(ArrayType::class);
        expect($collapsed->value)->toHaveCount(1);
        expect($collapsed->value[0]->isOptional())->toBeTrue();
    });

    it('gives the same keys whichever order the methods are written in', function () {
        $result = analyzeFile('app/Http/Requests/StoreGroupRequest.php')->result();

        expect($result->getMethod('contactRules')->returnType()->keys())->toBe(['email', 'city']);
        expect($result->getMethod('rules')->returnType()->keys())
            ->toBe(['name', 'price_amount', 'price_currency', 'note', 'email', 'city']);
    });
});

describe('a helper that can return an empty array', function () {
    it('makes the keys it only sometimes adds optional', function () {
        $returnType = analyzeFile('app/Spreading/Conditional.php')->result()->getMethod('toArray')->returnType();

        expect($returnType)->toBeInstanceOf(ArrayType::class);
        expect($returnType->value['id']->isOptional())->toBeFalse();
        expect($returnType->value['php_version']->isOptional())->toBeTrue();
        expect($returnType->value['node_version']->isOptional())->toBeTrue();
    });

    it('keeps the helper\'s empty branch next to its keyed one', function () {
        $returnType = analyzeFile('app/Spreading/Conditional.php')->result()->getMethod('whenSettings')->returnType();

        expect(Type::collapseArrays($returnType)->value['php_version']->isOptional())->toBeTrue();
    });
});
