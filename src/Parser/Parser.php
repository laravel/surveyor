<?php

namespace Laravel\Surveyor\Parser;

use Laravel\Surveyor\Analysis\Scope;
use Laravel\Surveyor\Resolvers\NodeResolver;
use Laravel\Surveyor\Support\Markers;
use Laravel\Surveyor\Visitors\TypeResolver;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Parser as PhpParserParser;
use PhpParser\PrettyPrinter\Standard;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use SplFileInfo;

class Parser
{
    public function __construct(
        protected Standard $prettyPrinter,
        protected NodeResolver $resolver,
        protected PhpParserParser $parser,
        protected NodeFinder $nodeFinder,
        protected NodeTraverser $nodeTraverser,
        protected TypeResolver $typeResolver,
    ) {
        $this->nodeTraverser->addVisitor(new NameResolver(null, ['preserveOriginalNames' => true]));
        $this->nodeTraverser->addVisitor($this->typeResolver);
    }

    /**
     * @return Scope[]
     */
    public function parse(
        string|ReflectionClass|ReflectionFunction|ReflectionMethod|SplFileInfo $code,
        string $path,
    ): array {
        $this->parseCode($code, $path);

        return [$this->flipScope($this->typeResolver->scope())];
    }

    protected function flipScope(Scope $scope)
    {
        while ($scope->parent()) {
            $scope = $scope->parent();
        }

        return $scope;
    }

    public function parseFile(string $path): array
    {
        return $this->parser->parse(file_get_contents($path));
    }

    protected function parseCode(
        string|ReflectionClass|ReflectionFunction|ReflectionMethod|SplFileInfo $code,
        string $path,
    ): array {
        $code = match (true) {
            is_string($code) => $code,
            $code instanceof SplFileInfo => file_get_contents($code->getPathname()),
            default => file_get_contents($code->getFileName()),
        };

        $this->typeResolver->newScope($path);
        $this->typeResolver->scope()->setIgnoreMarkerComments(Markers::markerComments($code));

        return $this->nodeTraverser->traverse($this->parser->parse($code));
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
