<?php

namespace Laravel\StaticAnalyzer\NodeResolvers;

use Laravel\StaticAnalyzer\Reflector\Reflector;
use Laravel\StaticAnalyzer\Resolvers\NodeResolver;
use Laravel\StaticAnalyzer\Parser\DocBlockParser;
use PhpParser\NodeAbstract;

abstract class AbstractResolver
{
    public function __construct(
        protected NodeResolver $resolver,
        protected DocBlockParser $docBlockParser,
        protected Reflector $reflector,
    ) {
        //
    }

    protected function from(NodeAbstract $node)
    {
        return $this->resolver->from($node);
    }
}
