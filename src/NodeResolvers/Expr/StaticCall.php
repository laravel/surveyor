<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use Laravel\StaticAnalyzer\Types\Type;
use PhpParser\Node;

class StaticCall extends AbstractResolver
{
    public function resolve(Node\Expr\StaticCall $node)
    {
        $class = $this->from($node->class);
        $method = $node->name->toString();

        $returnTypes = $this->reflector->methodReturnType($class, $method, $node);

        return Type::union(...$returnTypes);
    }
}
