<?php

namespace Laravel\StaticAnalyzer\NodeResolvers;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use Laravel\StaticAnalyzer\Result\Param as ResultParam;
use PhpParser\Node;

class Param extends AbstractResolver
{
    public function resolve(Node\Param $node)
    {
        return (new ResultParam(
            name: $node->var->name,
            type: $this->from($node->type),
            isVariadic: $node->variadic,
            isReference: $node->byRef,
        ))->fromNode($node);
    }
}
