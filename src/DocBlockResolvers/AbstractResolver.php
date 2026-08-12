<?php

namespace Laravel\Surveyor\DocBlockResolvers;

use Laravel\Surveyor\Analysis\Scope;
use Laravel\Surveyor\Debug\Debug;
use Laravel\Surveyor\Reflector\Reflector;
use Laravel\Surveyor\Resolvers\DocBlockResolver;
use PhpParser\Node\Expr\CallLike;
use PHPStan\PhpDocParser\Ast\Node;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;

abstract class AbstractResolver
{
    public function __construct(
        public DocBlockResolver $typeResolver,
        protected PhpDocNode $parsed,
        protected ?CallLike $referenceNode,
        protected Scope $scope,
        protected Reflector $reflector,
    ) {
        //
    }

    protected function from(Node $node)
    {
        if (Debug::$logLevel >= Debug::TRACE) {
            Debug::log('📄 Resolving DocBlock: '.get_class($node));
        }

        return $this->typeResolver
            ->setParsed($this->parsed)
            ->setReferenceNode($this->referenceNode)
            ->from($node, $this->scope);
    }
}
