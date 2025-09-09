<?php

namespace Laravel\StaticAnalyzer\Parser;

// use Laravel\StaticAnalyzer\Debug;

use Laravel\StaticAnalyzer\Resolvers\NodeResolver;
use Laravel\StaticAnalyzer\Visitors\TypeResolver;
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

    public function parse(string|ReflectionClass|ReflectionFunction|ReflectionMethod|SplFileInfo $code): array
    {
        return $this->parseCode($code);
    }

    protected function parseCode(string|ReflectionClass|ReflectionFunction|ReflectionMethod|SplFileInfo $code): array
    {
        // try {
        $code = match (true) {
            is_string($code) => $code,
            $code instanceof SplFileInfo => file_get_contents($code->getPathname()),
            default => file_get_contents($code->getFileName()),
        };

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
