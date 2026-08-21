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
    ) {
        //
    }

    /**
     * @return Scope[]
     */
    public function parse(
        string|ReflectionClass|ReflectionFunction|ReflectionMethod|SplFileInfo $code,
        string $path,
    ): array {
        return [$this->flipScope($this->parseCode($code, $path))];
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

    /**
     * Resolving a node can analyze another file, which lands back here while
     * this traversal is still running. The traverser and both visitors carry
     * the state of the file they are walking, so each parse gets its own set
     * rather than having the nested file overwrite the outer file's imports
     * halfway through.
     */
    protected function parseCode(
        string|ReflectionClass|ReflectionFunction|ReflectionMethod|SplFileInfo $code,
        string $path,
    ): Scope {
        $code = match (true) {
            is_string($code) => $code,
            $code instanceof SplFileInfo => file_get_contents($code->getPathname()),
            default => file_get_contents($code->getFileName()),
        };

        $typeResolver = new TypeResolver($this->resolver);
        $typeResolver->newScope($path);
        $typeResolver->scope()->setIgnoreMarkerComments(Markers::markerComments($code));

        $traverser = new NodeTraverser;
        $traverser->addVisitor(new NameResolver(null, ['preserveOriginalNames' => true]));
        $traverser->addVisitor($typeResolver);

        $traverser->traverse($this->parser->parse($code));

        return $typeResolver->scope();
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
