<?php

namespace Laravel\StaticAnalyzer\NodeResolvers;

use Laravel\StaticAnalyzer\Result\Param as ResultParam;
use Laravel\StaticAnalyzer\Types\Type;
use PhpParser\Node;

class Param extends AbstractResolver
{
    public function resolve(Node\Param $node)
    {
        $this->scope->variables()->add(
            $node->var->name,
            $node->type ? $this->from($node->type) : Type::null(),
            $node->getStartLine(),
        );

        return null;
        // return (new ResultParam(
        //     name: $node->var->name,
        //     type: $this->from($node->type),
        //     isVariadic: $node->variadic,
        //     isReference: $node->byRef,
        // ))->fromNode($node);
    }
}
