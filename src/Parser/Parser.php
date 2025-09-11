<?php

namespace Laravel\Surveyor\Parser;

// use Laravel\Surveyor\Debug;

use Laravel\Surveyor\Analysis\Scope;
use Laravel\Surveyor\Resolvers\NodeResolver;
use Laravel\Surveyor\Visitors\TypeResolver;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitor\ParentConnectingVisitor;
use PhpParser\Parser as PhpParserParser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use SplFileInfo;
use Throwable;

class Parser
{
    protected NodeFinder $nodeFinder;

    protected PhpParserParser $parser;

    protected NodeTraverser $nodeTraverser;

    protected array $cache = [];

    protected TypeResolver $typeResolver;

    public function __construct(
        protected Standard $prettyPrinter,
        protected NodeResolver $resolver,
    ) {
        $this->parser = (new ParserFactory)->createForHostVersion();
        $this->nodeFinder = new NodeFinder;
        $this->nodeTraverser = new NodeTraverser;
        // $this->nodeTraverser = new NodeTraverser(new ParentConnectingVisitor);
        $this->nodeTraverser->addVisitor(new NameResolver);

        $this->typeResolver = new TypeResolver($this->resolver);
        $this->nodeTraverser->addVisitor($this->typeResolver);
    }

    public function typeResolver()
    {
        return $this->typeResolver;
    }

    public function parse(string|ReflectionClass|ReflectionFunction|ReflectionMethod|SplFileInfo $code, string $path): array
    {
        $this->parseCode($code, $path);

        return array_map(fn ($scope) => $this->flipScope($scope), $this->typeResolver->scopes());
    }

    protected function flipScope(Scope $scope)
    {
        while ($scope->parent()) {
            $scope = $scope->parent();
        }

        return $scope;
    }

    protected function parseCode(string|ReflectionClass|ReflectionFunction|ReflectionMethod|SplFileInfo $code, string $path): array
    {
        // try {
        $code = match (true) {
            is_string($code) => $code,
            $code instanceof SplFileInfo => file_get_contents($code->getPathname()),
            default => file_get_contents($code->getFileName()),
        };

        $this->typeResolver->newScope($path);

        return $this->nodeTraverser->traverse($this->parser->parse($code));
        // } catch (Throwable $e) {
        //     // Debug::log("Error parsing code: {$e->getMessage()}", [
        //     //     'code' => $code,
        //     // ]);

        //     return [];
        // }
    }

    public function nodeFinder()
    {
        return $this->nodeFinder;
    }

    public function printer()
    {
        return $this->prettyPrinter;
    }
}
