<?php

namespace Laravel\Surveyor\DocBlockResolvers;

use Laravel\Surveyor\Analysis\Scope;
use Laravel\Surveyor\Debug\Debug;
use Laravel\Surveyor\Parser\DocBlockParser;
use Laravel\Surveyor\Reflector\Reflector;
use Laravel\Surveyor\Resolvers\DocBlockResolver;
use Laravel\Surveyor\Resolvers\NodeResolver;
use PhpParser\Node\Expr\CallLike;
use PHPStan\PhpDocParser\Ast\Node;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;

abstract class AbstractResolver
{
    public function __construct(
        public DocBlockResolver $typeResolver,
        protected DocBlockParser $docBlockParser,
        protected PhpDocNode $parsed,
        protected ?CallLike $referenceNode,
        protected Scope $scope,
        protected NodeResolver $nodeResolver,
        protected Reflector $reflector,
    ) {
        //
    }

    protected function from(Node $node)
    {
        Debug::log('📄 Resolving DocBlock: '.get_class($node), level: 3);

        return $this->typeResolver
            ->setParsed($this->parsed)
            ->setReferenceNode($this->referenceNode)
            ->from($node, $this->scope);
    }
}
