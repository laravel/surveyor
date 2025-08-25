<?php

namespace Laravel\StaticAnalyzer\Parser;

// use Laravel\StaticAnalyzer\Debug;
use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitor\ParentConnectingVisitor;
use PhpParser\NodeVisitorAbstract;
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

    public function __construct(
        protected Standard $prettyPrinter,
    ) {
        $this->parser = (new ParserFactory)->createForHostVersion();
        $this->nodeFinder = new NodeFinder;
        $this->nodeTraverser = new NodeTraverser(new ParentConnectingVisitor);
        $this->nodeTraverser->addVisitor(new NameResolver);
    }

    public function parse(string|ReflectionClass|ReflectionFunction|ReflectionMethod|SplFileInfo $code): array
    {
        $key = match (true) {
            is_string($code) => $code,
            $code instanceof SplFileInfo => $code->getPathname(),
            default => $code->getFileName(),
        };

        return $this->parseCode($code);

        // return $this->cache[$key] ??= $this->parseCode($code);
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

    public function walk(array $stmts, callable $onEnter, ?callable $onLeave = null): void
    {
        $visitor = new class($onEnter, $onLeave) extends NodeVisitorAbstract
        {
            public function __construct(
                protected $onEnter,
                protected $onLeave = null
            ) {}

            public function enterNode(Node $node)
            {
                return ($this->onEnter)($node);
            }

            public function leaveNode(Node $node)
            {
                if ($this->onLeave) {
                    return ($this->onLeave)($node);
                }
            }
        };

        $this->nodeTraverser->addVisitor($visitor);
        $this->nodeTraverser->traverse($stmts);
        $this->nodeTraverser->removeVisitor($visitor);
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
