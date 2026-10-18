<?php

use Laravel\Surveyor\Analyzer\AnalyzedCache;
use Laravel\Surveyor\Analyzer\Analyzer;
use Laravel\Surveyor\Parser\Parser;
use Laravel\Surveyor\Resolvers\NodeResolver;
use Laravel\Surveyor\Types\ArrayType;
use PhpParser\NodeFinder;
use PhpParser\Parser as PhpParserParser;
use PhpParser\PrettyPrinter\Standard;

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

describe('going over a file again', function () {
    beforeEach(function () {
        app()->singleton(Parser::class, fn ($app) => new class($app->make(Standard::class), $app->make(NodeResolver::class), $app->make(PhpParserParser::class), $app->make(NodeFinder::class)) extends Parser
        {
            /** @var array<string, int> */
            public array $passes = [];

            public function parse($code, string $path): array
            {
                $this->passes[basename($path)] = ($this->passes[basename($path)] ?? 0) + 1;

                return parent::parse($code, $path);
            }
        });
    });

    it('reads a file once when nothing in it asks for a method further down', function () {
        analyzeFile('app/Http/Requests/Concerns/HasPriceRules.php');

        expect(app(Parser::class)->passes['HasPriceRules.php'])->toBe(1);
    });

    it('reads a file once when the method further down has no literal to give', function () {
        analyzeFile('app/Spreading/Unanswerable.php');

        expect(app(Parser::class)->passes['Unanswerable.php'])->toBe(1);
    });

    it('reads a file again when a method further down returns a literal', function () {
        analyzeFile('app/Http/Requests/StoreGroupRequest.php');

        expect(app(Parser::class)->passes['StoreGroupRequest.php'])->toBeGreaterThan(1);
    });

    it('reads a file once when a method only asks for itself', function () {
        analyzeFile('app/Spreading/Recursive.php');

        expect(app(Parser::class)->passes['Recursive.php'])->toBe(1);
    });

    it('stops going over two methods that spread each other', function () {
        analyzeFile('app/Spreading/Circular.php');

        expect(app(Parser::class)->passes['Circular.php'])->toBe(2);
    });
});
