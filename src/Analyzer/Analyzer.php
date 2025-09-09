<?php

namespace Laravel\StaticAnalyzer\Analyzer;

use Laravel\StaticAnalyzer\Analysis\Scope;
use Laravel\StaticAnalyzer\Debug\Debug;
use Laravel\StaticAnalyzer\Parser\Parser;
use Laravel\StaticAnalyzer\Resolvers\NodeResolver;
use Laravel\StaticAnalyzer\Result\ClassDeclaration;

class Analyzer
{
    protected array $analyzed = [];

    protected Scope $scope;

    public function __construct(
        protected Parser $parser,
        protected NodeResolver $resolver,
    ) {
        //
    }

    public function analyze(string $path)
    {
        $cached = AnalyzedCache::get($path);

        if ($cached) {
            dd('hit it');

            return $cached;
        }

        if (AnalyzedCache::isInProgress($path)) {
            return;
        }

        AnalyzedCache::inProgress($path);

        if ($path === '') {
            Debug::log('⚠️  No path provided to analyze.');

            return $this;
        }

        Debug::log('🧠 Analyzing: '.$path);

        $parsed = $this->parser->parse(file_get_contents($path));

        dd($this->parser->typeResolver()->scope());

        // $this->scope = new Scope;

        // $this->analyzed = collect($parsed)
        //     ->map(fn ($node) => $this->resolver->from($node, $this->scope))
        //     ->map(fn ($nodes) => array_values(array_filter($nodes)))
        //     ->all();

        // AnalyzedCache::add($path, $this->analyzed);

        return $this;
    }

    public function scope(): Scope
    {
        return $this->scope;
    }

    public function analyzed()
    {
        return $this->analyzed;
    }

    public function methodReturnType(string $className, string $methodName)
    {
        $class = collect($this->analyzed)
            ->flatten(1)
            ->first(fn ($type) => $type instanceof ClassDeclaration && $type->name === $className);

        assert($class !== null);

        $method = collect($class->methods)->first(fn ($method) => $method->name === $methodName);

        assert($method !== null);

        return $method->returnTypes;
    }
}
