<?php

namespace Laravel\StaticAnalyzer\Resolvers;

// use Laravel\StaticAnalyzer\Debug;
use Illuminate\Container\Container;
use PhpParser\Node\Expr\CallLike;
use PHPStan\PhpDocParser\Ast\Node;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;

class DocBlockResolver
{
    protected PhpDocNode $parsed;

    protected ?CallLike $referenceNode = null;

    public function __construct(
        protected Container $container,
    ) {
        //
    }

    public function setReferenceNode(?CallLike $node = null): self
    {
        $this->referenceNode = $node;

        return $this;
    }

    public function setParsed(PhpDocNode $parsed): self
    {
        $this->parsed = $parsed;

        return $this;
    }

    public function from(Node $node, array $context = [])
    {
        $className = str(get_class($node))->after('Ast\\')->prepend('Laravel\\StaticAnalyzer\\DocBlockResolvers\\')->toString();

        if (! class_exists($className)) {
            dd("Class {$className} does not exist");
        }

        // Debug::log("Resolving {$className}");

        return $this->container->make($className, [
            // 'typeResolver' => $this,
            // 'context' => $context,
            'parsed' => $this->parsed,
            'referenceNode' => $this->referenceNode,
        ])->resolve($node);
    }
}
